<?php

namespace IPE;

class PHPInfo
{
    /**
     * @var non-empty-string
     *
     * @example '/usr/bin/php'
     * @example 'C:/Path/To/php/php.exe'
     */
    private $phpBin;

    /**
     * @var non-empty-string
     *
     * @example '8.4.14'
     */
    private $version;

    /**
     * @var non-empty-string
     *
     * @example '8.1.2' for '8.1.2-1ubuntu2.14'
     */
    private $baseVersion;

    /**
     * @var int
     *
     * @example 8 for '8.4.14'
     */
    private $majorVersion;

    /**
     * @var int
     *
     * @example 804 for '8.4.14'
     */
    private $majorMinorVersion;

    /**
     * @var int
     *
     * @example 20240924
     */
    private $apiVersion;

    /**
     * @var int
     */
    private $bits;

    /**
     * @var bool
     */
    private $threadSafe;

    /**
     * @var non-empty-string
     */
    private $extensionDir;

    /**
     * @var string
     */
    private $iniScanDir;

    /**
     * @var list<non-empty-string>
     */
    private $iniScanDirs;

    /**
     * @var string
     */
    private $loadedIniFile;

    /**
     * @var string
     */
    private $configureCommand;

    /**
     * @var string[]
     */
    private $installedExtensions = [];

    final protected function __construct()
    {
    }

    /**
     * Describe the PHP that's running this code.
     *
     * @param \IPE\Filesystem $fs the local filesystem
     *
     * @throws \IPE\Exception\InvalidArgument if $fs is not local
     * @throws \IPE\Exception\IO
     *
     * @return static
     */
    public static function fromCurrentPHP(Filesystem $fs)
    {
        if (!$fs->isLocal()) {
            throw new Exception\InvalidArgument('The running PHP can only be described with a local filesystem');
        }
        $result = new static();
        $result->phpBin = $fs->getRealPath(PHP_BINARY);
        $result->setVersion(PHP_VERSION);
        $result->majorVersion = PHP_MAJOR_VERSION;
        $result->majorMinorVersion = PHP_MAJOR_VERSION * 100 + PHP_MINOR_VERSION;
        $result->bits = 8 * PHP_INT_SIZE;
        $result->threadSafe = ZEND_THREAD_SAFE;
        $extensionDir = ini_get('extension_dir');
        if ($extensionDir === false || $extensionDir === '') {
            throw new Exception\IO('Unable to determine the PHP extension directory');
        }
        $result->extensionDir = $fs->getRealPath($extensionDir);
        ob_start();
        phpinfo(INFO_GENERAL);
        $info = ob_get_clean();
        if ($info === false) {
            throw new Exception\IO('Failed to collect the general PHP info');
        }
        $result->parseGeneralInfo($info, PHP_CONFIG_FILE_SCAN_DIR, $fs);
        $installedExtensions = [];
        foreach ([false, true] as $zendExtensionFlag) {
            $installedExtensions = array_merge($installedExtensions, get_loaded_extensions($zendExtensionFlag));
        }
        $result->setInstalledExtensions($installedExtensions);

        return $result;
    }

    /**
     * @param non-empty-string $phpBin the name of the php command (looked for in the PATH), or the path of the PHP binary
     *
     * @throws \IPE\Exception\InvalidArgument if $phpBin can't be found
     * @throws \IPE\Exception\IO
     *
     * @return static
     *
     * @example fromPHPBin('php8.4', $fs)
     * @example fromPHPBin('/usr/local/bin/php', $fs)
     */
    public static function fromPHPBin($phpBin, Filesystem $fs)
    {
        $result = new static();
        if (strpbrk($phpBin, '/\\') === false) {
            $path = $fs->resolveCommand($phpBin);
            if ($path === '') {
                throw new Exception\InvalidArgument("Unable to find the PHP command '{$phpBin}'");
            }
        } else {
            if (!$fs->isFile($phpBin)) {
                throw new Exception\InvalidArgument("The PHP binary '{$phpBin}' does not exist");
            }
            $path = $phpBin;
        }
        $result->phpBin = $fs->getRealPath($path);
        $safeArguments = self::getSafeRunArguments($fs);
        list($rc, $output) = $fs->run(Filesystem\Command::create(
            $result->phpBin,
            array_merge($safeArguments, [
                '-r', 'echo ' . implode(',PHP_EOL,', [
                    'PHP_VERSION',
                    'PHP_MAJOR_VERSION',
                    'PHP_MINOR_VERSION',
                    'PHP_INT_SIZE',
                    'ZEND_THREAD_SAFE ? 1 : 0',
                    "ini_get('extension_dir')",
                    'PHP_CONFIG_FILE_SCAN_DIR',
                    'implode(PHP_EOL, get_loaded_extensions(false))',
                    'implode(PHP_EOL, get_loaded_extensions(true))',
                ]) . ';',
            ])
        )->withOutput(Filesystem\Command::OUTPUT_CAPTURE_STDOUT));
        if ($rc !== 0) {
            throw new Exception\IO('Failed to inspect the specified php.');
        }
        // Empty lines are kept: PHP_CONFIG_FILE_SCAN_DIR may be empty
        $lines = Service\Regex::split('/\r?\n/', $output);
        if (count($lines) < 7 || $lines[0] === '' || $lines[5] === '') {
            throw new Exception\IO("Failed to inspect the specified php: unexpected output\n{$output}");
        }
        $result->setVersion($lines[0]);
        $result->majorVersion = (int) $lines[1];
        $result->majorMinorVersion = $result->majorVersion * 100 + (int) $lines[2];
        $result->bits = 8 * (int) $lines[3];
        $result->threadSafe = (bool) $lines[4];
        $result->extensionDir = $fs->getRealPath($lines[5]);
        list($rc, $info) = $fs->run(Filesystem\Command::create($result->phpBin, array_merge($safeArguments, ['-i']))->withOutput(Filesystem\Command::OUTPUT_CAPTURE_STDOUT));
        if ($rc !== 0) {
            throw new Exception\IO('Failed to collect the general info of the specified php.');
        }
        $result->parseGeneralInfo($info, $lines[6], $fs);
        $result->setInstalledExtensions(array_slice($lines, 7));

        return $result;
    }

    /**
     * @return non-empty-string
     *
     * @example '/usr/bin/php'
     * @example 'C:/Path/To/php/php.exe'
     */
    public function getPHPBin()
    {
        return $this->phpBin;
    }

    /**
     * Get the directory where the extensions are installed.
     *
     * @return non-empty-string
     */
    public function getExtensionDir()
    {
        return $this->extensionDir;
    }

    /**
     * Get the directory where the additional ini files should be written (it's one of the directories returned by getIniScanDirs()).
     *
     * @return string empty string if PHP doesn't scan any directory
     */
    public function getIniScanDir()
    {
        return $this->iniScanDir;
    }

    /**
     * Get all the directories scanned by PHP for additional ini files (including the ones that don't exist).
     *
     * @return list<non-empty-string>
     */
    public function getIniScanDirs()
    {
        return $this->iniScanDirs;
    }

    /**
     * @return string empty string if no php.ini is loaded
     */
    public function getLoadedIniFile()
    {
        return $this->loadedIniFile;
    }

    /**
     * Get the command used to configure PHP when it was compiled.
     *
     * @return string
     *
     * @example "'./configure' '--with-libdir=lib64' '--enable-fpm'"
     */
    public function getConfigureCommand()
    {
        return $this->configureCommand;
    }

    /**
     * @return non-empty-string
     *
     * @example '8.4.14'
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Get the version without the suffixes of the distributions and of the pre-releases, so that it can be compared with the requirements of the packages.
     *
     * @return non-empty-string
     *
     * @example '8.1.2' for '8.1.2-1ubuntu2.14'
     * @example '8.5.0' for '8.5.0RC1'
     */
    public function getBaseVersion()
    {
        return $this->baseVersion;
    }

    /**
     * @return int
     *
     * @example 8 for '8.4.14'
     */
    public function getMajorVersion()
    {
        return $this->majorVersion;
    }

    /**
     * @return int
     *
     * @example 804 for '8.4.14'
     */
    public function getMajorMinorVersion()
    {
        return $this->majorMinorVersion;
    }

    /**
     * Get the PHP API version (the one that phpize and php-config must match).
     *
     * @return int
     *
     * @example 20240924
     */
    public function getAPIVersion()
    {
        return $this->apiVersion;
    }

    /**
     * @return int
     *
     * @example 32
     * @example 64
     */
    public function getBits()
    {
        return $this->bits;
    }

    /**
     * @return bool
     */
    public function isThreadSafe()
    {
        return $this->threadSafe;
    }

    /**
     * @return string[]
     */
    public function getInstalledExtensions()
    {
        return $this->installedExtensions;
    }

    /**
     * @return bool
     */
    public function isExtensionInstalled(Extension $extension)
    {
        $installedNames = array_map('strtolower', $this->installedExtensions);
        $extensionNames = array_map('strtolower', $extension::getInstalledNames());

        return array_intersect($installedNames, $extensionNames) !== [];
    }

    /**
     * @return $this
     */
    public function markExtensionAsInstalled(Extension $extension)
    {
        $names = $extension::getInstalledNames();

        return $this->addInstalledExtensions([$names[0]]);
    }

    /**
     * @param string[] $names the names of the extensions as seen by PHP
     *
     * @return $this
     */
    public function addInstalledExtensions(array $names)
    {
        return $this->setInstalledExtensions(array_merge($this->getInstalledExtensions(), $names));
    }

    /**
     * The php arguments that disable the output of startup errors and of Xdebug.
     *
     * @return list<string>
     */
    private static function getSafeRunArguments(Filesystem $fs)
    {
        return [
            '-d', 'display_startup_errors=0',
            '-d', 'error_reporting=0',
            '-d', $fs->getDirectorySeparator() === '\\' ? 'display_errors=NULL' : 'display_errors=/dev/null',
            // Xdebug3
            '-d', 'xdebug.mode=off',
            // Xdebug2
            '-d', 'xdebug.default_enable=0',
            '-d', 'xdebug.remote_autostart=0',
            '-d', 'xdebug.remote_enable=0',
            '-d', 'xdebug.remote_host=unix:///dev/null',
            '-d', 'xdebug.profiler_enable=0',
        ];
    }

    /**
     * @param string $version
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function setVersion($version)
    {
        // The distributions add their suffixes (for example 8.1.2-1ubuntu2.14), which composer/semver doesn't accept
        $matches = null;
        if (!preg_match('/^\d+\.\d+\.\d+/', $version, $matches)) {
            throw new Exception\IO("Unrecognized PHP version: '{$version}'");
        }
        $this->version = $version;
        $this->baseVersion = $matches[0];
    }

    /**
     * Read the values of interest from the output of phpinfo(INFO_GENERAL) / php -i.
     *
     * @param string $info
     * @param string $defaultIniScanDir the value of PHP_CONFIG_FILE_SCAN_DIR
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function parseGeneralInfo($info, $defaultIniScanDir, Filesystem $fs)
    {
        $api = self::readInfoValue($info, 'PHP API');
        if (!preg_match('/^\d+$/D', $api)) {
            throw new Exception\IO("Failed to determine the PHP API version from the general PHP info\n{$info}");
        }
        $this->apiVersion = (int) $api;
        $this->configureCommand = self::readInfoValue($info, 'Configure Command');
        list($this->iniScanDir, $this->iniScanDirs) = self::parseIniScanDirs(self::readInfoValue($info, 'Scan this dir for additional .ini files'), $defaultIniScanDir, $fs);
        $loadedIniFile = self::readInfoValue($info, 'Loaded Configuration File');
        $this->loadedIniFile = $loadedIniFile === '' ? '' : $fs->getRealPath($loadedIniFile);
    }

    /**
     * The PHP_INI_SCAN_DIR environment variable may contain a list of directories, where an empty one is the directory PHP has been compiled with.
     * We write to the directory PHP has been compiled with if it's in the list, otherwise to the first existing one.
     *
     * @param string $scanDirs
     * @param string $defaultScanDir the value of PHP_CONFIG_FILE_SCAN_DIR
     *
     * @throws \IPE\Exception\IO
     *
     * @return array{string, list<non-empty-string>} the directory where to write (empty string if PHP doesn't scan any directory), and all the scanned directories
     *
     * @see https://www.php.net/manual/en/configuration.file.php#configuration.file.scan
     */
    private static function parseIniScanDirs($scanDirs, $defaultScanDir, Filesystem $fs)
    {
        if ($scanDirs === '') {
            return ['', []];
        }
        $default = $defaultScanDir === '' ? '' : self::normalizeDirectory($defaultScanDir, $fs);
        $directories = [];
        foreach (explode($fs->getPathSeparator(), $scanDirs) as $directory) {
            $directory = $directory === '' ? $default : self::normalizeDirectory($directory, $fs);
            if ($directory !== '' && !in_array($directory, $directories, true)) {
                $directories[] = $directory;
            }
        }
        if ($directories === []) {
            return ['', []];
        }
        if ($default !== '' && in_array($default, $directories, true)) {
            return [$default, $directories];
        }
        foreach ($directories as $directory) {
            if ($fs->exists($directory)) {
                return [$directory, $directories];
            }
        }

        return [$directories[0], $directories];
    }

    /**
     * Paths are normalized because PHP may have been launched through a symlink.
     *
     * @param non-empty-string $directory
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    private static function normalizeDirectory($directory, Filesystem $fs)
    {
        return $fs->exists($directory) ? $fs->getRealPath($directory) : $directory;
    }

    /**
     * @param string $info
     * @param non-empty-string $label
     *
     * @return string empty string if the label is missing, or if its value is "(none)"
     */
    private static function readInfoValue($info, $label)
    {
        $matches = null;
        if (!preg_match('/^' . preg_quote($label, '/') . '\s*=>\s*(?<value>.*?)\s*$/m', $info, $matches)) {
            return '';
        }

        return $matches['value'] === '(none)' ? '' : $matches['value'];
    }

    /**
     * @param string[] $value
     *
     * @return $this
     */
    protected function setInstalledExtensions(array $value)
    {
        $value = array_map('trim', $value);
        $value = array_filter($value, static function ($line) { return $line !== ''; });
        $value = array_unique($value, SORT_STRING);
        natcasesort($value);
        $this->installedExtensions = array_values($value);

        return $this;
    }
}
