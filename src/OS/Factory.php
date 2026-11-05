<?php

namespace IPE\OS;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Service\Container;
use IPE\Service\Regex;

class Factory
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var \IPE\Service\Container
     */
    private $app;

    public function __construct(Container $app, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->app = $app;
    }

    /**
     * Get the classes of the known operating systems: this is the only place where they are listed.
     *
     * @return list<class-string<\IPE\OS>>
     */
    protected function getOSClasses()
    {
        return [Alpine::class, Debian::class, Ubuntu::class, Windows::class];
    }

    /**
     * @param string $identifier
     *
     * @return class-string<\IPE\OS>|'' empty string if the identifier is unknown
     */
    public function getOSClassByIdentifier($identifier)
    {
        foreach ($this->getOSClasses() as $class) {
            if ($class::getIdentifier() === $identifier) {
                return $class;
            }
        }

        return '';
    }

    /**
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\OS
     */
    public function detectOS()
    {
        $fs = $this->app->make(Filesystem::class);
        if ($fs->getDirectorySeparator() !== '/') {
            return $this->buildWindows($fs);
        }
        if ($fs->isFile('/etc/os-release')) {
            return $this->buildFromOSRelease($fs);
        }
        throw new Exception\UnsupportedOS('Failed to detect the operating system');
    }

    /**
     * Detect Windows with PowerShell (WMIC is no longer available on recent Windows versions), falling back to php_uname().
     *
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\OS\Windows
     */
    private function buildWindows(Filesystem $fs)
    {
        $data = $this->readWindowsDataWithPowerShell($fs);
        if ($data === null) {
            if (!$fs->isLocal()) {
                throw new Exception\UnsupportedOS('Failed to detect the Windows version: PowerShell is required on remote systems');
            }
            $this->logger->debug('Detecting Windows with php_uname()');
            $data = self::readWindowsDataFromPHP();
        }
        try {
            return $this->app->build(Windows::class, $data);
        } catch (Exception\InvalidArgument $x) {
            throw new Exception\UnsupportedOS("Failed to detect the Windows version: {$x->getMessage()}\nDetected data:\n" . print_r($data, true));
        }
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return array{caption: string, bits: int, version: string}|null
     */
    private function readWindowsDataWithPowerShell(Filesystem $fs)
    {
        $script = implode('; ', [
            '& { try { Get-CimInstance -ClassName Win32_OperatingSystem -ErrorAction Stop } catch { Get-WmiObject -Class Win32_OperatingSystem } }',
            'ForEach-Object { \'Caption=\' + $_.Caption; \'Version=\' + $_.Version; \'OSArchitecture=\' + $_.OSArchitecture }',
        ]);
        $script = str_replace('; ForEach-Object', ' | ForEach-Object', $script);
        foreach (['pwsh', 'powershell'] as $try) {
            $command = $fs->resolveCommand($try);
            if ($command === '') {
                continue;
            }
            $this->logger->debug("Detecting Windows with {$command}");
            list($rc, $output) = $fs->run(Command::create($command, ['-NoProfile', '-NonInteractive', '-EncodedCommand', self::encodePowerShellScript($script)])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
            if ($rc !== 0) {
                $this->logger->debug("{$command} failed with return code {$rc}:\n{$output}");
                continue;
            }
            $data = [];
            foreach (Regex::split('/[\r\n]+/', $output, -1, true) as $line) {
                list($key, $value) = explode('=', $line, 2) + [1 => ''];
                $data[trim($key)] = trim($value);
            }
            $matches = null;
            if (
                isset($data['Caption'], $data['Version'], $data['OSArchitecture'])
                && $data['Caption'] !== ''
                && $data['Version'] !== ''
                && preg_match('/^(?<bits>[1-9]\d{0,2})[\s-]?bit/i', $data['OSArchitecture'], $matches)
            ) {
                return [
                    'caption' => $data['Caption'],
                    'bits' => (int) $matches['bits'],
                    'version' => $data['Version'],
                ];
            }
            $this->logger->debug("Unable to parse the output of {$command}:\n{$output}");
        }

        return null;
    }

    /**
     * Describe the Windows system where PHP is running (so it's only meaningful for local filesystems).
     *
     * @return array{caption: string, bits: int, version: string}
     */
    private static function readWindowsDataFromPHP()
    {
        $matches = null;
        $versionDetails = php_uname('v');
        $caption = preg_match('/\((?<name>[^()]+)\)/', $versionDetails, $matches) ? 'Microsoft ' . trim($matches['name']) : 'Microsoft Windows';
        $version = php_uname('r');
        if (preg_match('/\bbuild (?<build>\d+)/i', $versionDetails, $matches)) {
            $version .= '.' . $matches['build'];
        }
        $architecture = getenv('PROCESSOR_ARCHITEW6432') ?: getenv('PROCESSOR_ARCHITECTURE') ?: '';
        if ($architecture !== '') {
            $bits = preg_match('/64/', $architecture) ? 64 : 32;
        } else {
            $bits = 8 * PHP_INT_SIZE;
        }

        return [
            'caption' => $caption,
            'bits' => $bits,
            'version' => $version,
        ];
    }

    /**
     * Encode a PowerShell script for the -EncodedCommand argument (base64 of UTF-16LE), so that we don't have to escape it.
     *
     * @param string $script ASCII only
     *
     * @return string
     */
    private static function encodePowerShellScript($script)
    {
        $utf16 = '';
        foreach (str_split($script) as $char) {
            $utf16 .= $char . "\0";
        }

        return base64_encode($utf16);
    }

    /**
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\OS
     */
    private function buildFromOSRelease(Filesystem $fs)
    {
        $data = $this->readOSReleaseFile($fs);
        $id = isset($data['ID']) ? $data['ID'] : '';
        if ($id === '') {
            throw new Exception\UnsupportedOS("'/etc/os-release' does not define the distribution ID");
        }
        $class = $this->getOSClassByIdentifier($id);
        if ($class === '' || !is_subclass_of($class, Posix::class)) {
            throw new Exception\UnsupportedOS("'/etc/os-release' defines an unsupported distribution ({$id})");
        }

        return $class::fromOSRelease($data, $this->app);
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return array<non-empty-string, string>
     */
    private function readOSReleaseFile(Filesystem $fs)
    {
        $data = $fs->readFile('/etc/os-release');
        $result = [];
        $lines = Regex::split('/[\r\n]+/', $data, -1, true);
        $matches = null;
        foreach ($lines as $line) {
            if (!\preg_match('/^\s*(?<key>\w+)\s*=\s*(?<value>\S(.*\S)?)\s*$/D', $line, $matches)) {
                continue;
            }
            $key = $matches['key'];
            $value = $matches['value'];
            if (isset($value[1]) && ($value[0] === '"' || $value[0] === "'") && $value[strlen($value) - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
