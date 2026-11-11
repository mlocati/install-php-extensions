<?php

namespace IPE\Installer;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\PHPInfo;
use IPE\Service\SizeFormatter;

/**
 * Locates the tools needed to compile PHP extensions (phpize, php-config, make).
 */
final class BuildTools
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var \IPE\PHPInfo
     */
    private $php;

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var non-empty-string|null
     */
    private $phpize = null;

    /**
     * @var string|null
     */
    private $phpConfig = null;

    /**
     * @var string|null
     */
    private $includeDir = null;

    /**
     * @var non-empty-string|null
     */
    private $make = null;

    /**
     * @var positive-int|null
     */
    private $processorCount = null;

    /**
     * @var string|null
     */
    private $strip = null;

    /**
     * @var string|null
     */
    private $pkgConfig = null;

    /**
     * @var non-empty-string|null
     */
    private $stackProtectorFlag = null;

    public function __construct(PHPInfo $php, Filesystem $fs, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->php = $php;
        $this->fs = $fs;
    }

    /**
     * Get the path of the phpize script matching the PHP API version.
     *
     * @throws \IPE\Exception\IO if phpize can't be found
     *
     * @return non-empty-string
     */
    public function getPhpize()
    {
        if ($this->phpize === null) {
            $phpize = $this->findTool('phpize', function ($candidate) {
                list($rc, $output) = $this->fs->run(Command::create($candidate, ['--version']));
                $matches = null;

                return $rc === 0 && preg_match('/PHP Api Version:\s*(?<api>\d+)/i', $output, $matches) && (int) $matches['api'] === $this->php->getAPIVersion();
            });
            if ($phpize === '') {
                throw new Exception\IO("Unable to find phpize for PHP {$this->php->getVersion()} (API {$this->php->getAPIVersion()}): please install the PHP development files (and autoconf)");
            }
            $this->phpize = $phpize;
        }

        return $this->phpize;
    }

    /**
     * Get the path of the php-config script matching the PHP version.
     *
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if not found
     */
    public function getPhpConfig()
    {
        if ($this->phpConfig === null) {
            // php-config doesn't provide the API version
            $this->phpConfig = $this->findTool('php-config', function ($candidate) {
                list($rc, $output) = $this->fs->run(Command::create($candidate, ['--version']));

                return $rc === 0 && trim($output) === $this->php->getVersion();
            });
        }

        return $this->phpConfig;
    }

    /**
     * Get the directory of the PHP headers.
     *
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if it can't be determined
     *
     * @example '/usr/include/php/20131226'
     */
    public function getIncludeDir()
    {
        if ($this->includeDir === null) {
            $this->includeDir = '';
            $phpConfig = $this->getPhpConfig();
            if ($phpConfig !== '') {
                list($rc, $output) = $this->fs->run(Command::create($phpConfig, ['--include-dir']));
                if ($rc === 0) {
                    $this->includeDir = trim($output);
                }
            }
        }

        return $this->includeDir;
    }

    /**
     * Get the library directory PHP was configured with (--with-libdir).
     *
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if PHP was configured without it and if it's not needed
     *
     * @example 'lib64'
     * @example 'lib/x86_64-linux-gnu'
     */
    public function getLibdir()
    {
        $matches = null;
        if (preg_match('/--with-libdir=(?<libdir>[^\s\'"]+)/', $this->php->getConfigureCommand(), $matches)) {
            return $matches['libdir'];
        }
        // Debian stores the libraries in a multiarch directory: the configure scripts of PHP up to 8.0 often don't look there (for example, the one of pdo_dblib), and both the Docker images of PHP 5.5 and the system PHP packages don't specify it
        if ($this->php->getMajorMinorVersion() > 800 || $this->fs->resolveCommand('gcc') === '') {
            return '';
        }
        list($rc, $output) = $this->fs->run(Command::create('gcc', ['-print-multiarch']));
        $multiarch = trim($output);

        return $rc === 0 && $multiarch !== '' ? "lib/{$multiarch}" : '';
    }

    /**
     * @throws \IPE\Exception\IO if make can't be found
     *
     * @return non-empty-string
     */
    public function getMake()
    {
        if ($this->make === null) {
            foreach (['make', 'gmake'] as $name) {
                $resolved = $this->fs->resolveCommand($name);
                if ($resolved !== '') {
                    $this->make = $resolved;
                    break;
                }
            }
            if ($this->make === null) {
                throw new Exception\IO('Unable to find the make command: please install it');
            }
        }

        return $this->make;
    }

    /**
     * Is a library available for compiling? It's what the configure scripts use to find libraries, wherever they are installed.
     *
     * @param non-empty-string $module the name of the pkg-config module, optionally with a version requirement (eg 'libavif >= 1.0')
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    public function hasPkgConfigModule($module)
    {
        return self::hasPkgConfigModuleFor($this->fs, $module);
    }

    /**
     * Same as hasPkgConfigModule(), for callers that don't have a BuildTools instance.
     *
     * @param non-empty-string $module the name of the pkg-config module, optionally with a version requirement (eg 'libavif >= 1.0')
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    public static function hasPkgConfigModuleFor(Filesystem $fs, $module)
    {
        foreach (['pkg-config', 'pkgconf'] as $command) {
            if ($fs->resolveCommand($command) !== '') {
                list($rc) = $fs->run(Command::create($command, ['--exists', $module])->withOutput(Command::OUTPUT_DISCARD));

                return $rc === 0;
            }
        }

        return self::hasPkgConfigFile($fs, $module);
    }

    /**
     * pkg-config is a build dependency: it's usually missing before the build dependencies are installed (and it's removed with them), so we look for its files.
     *
     * @param non-empty-string $module
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    private static function hasPkgConfigFile(Filesystem $fs, $module)
    {
        $matches = null;
        if (!preg_match('/^(?<name>[^\s>=]+)(\s*>=\s*(?<version>\S+))?$/D', $module, $matches)) {
            return false;
        }
        foreach (['/usr/local/lib/pkgconfig', '/usr/local/share/pkgconfig', '/usr/lib/pkgconfig', '/usr/share/pkgconfig'] as $directory) {
            $file = "{$directory}/{$matches['name']}.pc";
            if (!$fs->isFile($file)) {
                continue;
            }
            if (!isset($matches['version'])) {
                return true;
            }
            $versionMatches = null;

            return preg_match('/^Version:\s*(?<version>\S+)/m', $fs->readFile($file), $versionMatches) === 1 && version_compare($versionMatches['version'], $matches['version'], '>=');
        }

        return false;
    }

    /**
     * Check if a library can be used for compiling, by compiling a test program (like the configure scripts do): to be used for libraries without a pkg-config module.
     * Before the build dependencies are installed, the compiler may be missing: in that case we can't tell, and the answer is false.
     *
     * @param non-empty-string $library the name of the library for the linker (eg 'yuv' for libyuv.so)
     * @param non-empty-string $header a header file of the library
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    public function canLinkLibrary($library, $header)
    {
        foreach (['cc', 'gcc'] as $compiler) {
            if ($this->fs->resolveCommand($compiler) !== '') {
                $directory = $this->fs->createVolatileDirectory();
                $source = $directory->getPath() . '/test.c';
                $this->fs->writeFile($source, "#include <{$header}>\nint main(void)\n{\n    return 0;\n}\n");
                list($rc) = $this->fs->run(Command::create($compiler, [$source, "-l{$library}", '-o', $directory->getPath() . '/test'])->withOutput(Command::OUTPUT_DISCARD));

                return $rc === 0;
            }
        }

        return false;
    }

    /**
     * -fstack-protector-strong requires GCC 4.9 (Ubuntu 14.04 has GCC 4.8).
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    public function getStackProtectorFlag()
    {
        if ($this->stackProtectorFlag === null) {
            $this->stackProtectorFlag = $this->compilerAcceptsFlag('-fstack-protector-strong') ? '-fstack-protector-strong' : '-fstack-protector';
        }

        return $this->stackProtectorFlag;
    }

    /**
     * The environment variables needed by the configure scripts generated by phpize (also when the build scripts of the vendors run phpize).
     *
     * @throws \IPE\Exception\IO
     *
     * @return array<non-empty-string, string>
     */
    public function getConfigureEnvironment()
    {
        $result = [];
        if ($this->php->getMajorMinorVersion() < 704) {
            // The phpize of PHP up to 7.3 uses $SED before looking for it: with autoconf 2.72 it's empty, so the include flags are lost
            if (getenv('SED') === false) {
                $result['SED'] = 'sed';
            }
            // Sury's PHP_SETUP_LIBXML uses pkg-config: when config.m4 calls it in more branches, only the first one looks for pkg-config.
            // It must be a path: PHP_SETUP_OPENSSL checks it with "test -x"
            if (getenv('PKG_CONFIG') === false) {
                $pkgConfig = $this->getPkgConfig();
                if ($pkgConfig !== '') {
                    $result['PKG_CONFIG'] = $pkgConfig;
                }
            }
        }

        return $result;
    }

    /**
     * @param non-empty-string $flag
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool true if the compiler is missing (configure will report it)
     */
    private function compilerAcceptsFlag($flag)
    {
        foreach (['cc', 'gcc'] as $compiler) {
            if ($this->fs->resolveCommand($compiler) !== '') {
                $directory = $this->fs->createVolatileDirectory();
                $source = $directory->getPath() . '/test.c';
                $this->fs->writeFile($source, "int main(void)\n{\n    return 0;\n}\n");
                list($rc) = $this->fs->run(Command::create($compiler, [$flag, '-c', $source, '-o', $directory->getPath() . '/test.o'])->withOutput(Command::OUTPUT_DISCARD));

                return $rc === 0;
            }
        }

        return true;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return string the path of the strip command (empty string if not available)
     */
    public function getStrip()
    {
        if ($this->strip === null) {
            $this->strip = $this->fs->resolveCommand('strip');
        }

        return $this->strip;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return string the path of the pkg-config command (empty string if not available)
     */
    public function getPkgConfig()
    {
        if ($this->pkgConfig === null) {
            $this->pkgConfig = $this->fs->resolveCommand('pkg-config');
        }

        return $this->pkgConfig;
    }

    /**
     * Remove the debug symbols from a module, to reduce its size.
     *
     * @param non-empty-string $modulePath
     * @param non-empty-string $workingDirectory
     *
     * @throws \IPE\Exception\IO if the strip command is not available or if it fails
     *
     * @return void
     */
    public function stripModule($modulePath, $workingDirectory)
    {
        $strip = $this->getStrip();
        if ($strip === '') {
            throw new Exception\IO('The strip command is not available');
        }
        $sizeBefore = $this->fs->getFileSize($modulePath);
        list($rc) = $this->fs->run(Command::create($strip, ['--strip-all', $modulePath])->withWorkingDirectory($workingDirectory)->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\IO("strip failed with exit code {$rc}");
        }
        $sizeAfter = $this->fs->getFileSize($modulePath);
        $this->logger->info("Removed the debug symbols from {$modulePath}: size reduced from " . SizeFormatter::format($sizeBefore) . ' to ' . SizeFormatter::format($sizeAfter) . ' - ' . SizeFormatter::format($sizeBefore - $sizeAfter) . ' saved');
    }

    /**
     * Get the number of parallel jobs to be used for the compilation.
     *
     * @throws \IPE\Exception\IO
     *
     * @return positive-int
     */
    public function getParallelJobs(BuildOptions $buildOptions)
    {
        return $buildOptions->getParallelJobs() ?: $this->getProcessorCount();
    }

    /**
     * Get the number of processors available for the compilation.
     *
     * @throws \IPE\Exception\IO
     *
     * @return positive-int
     */
    public function getProcessorCount()
    {
        if ($this->processorCount === null) {
            $this->processorCount = $this->detectProcessorCount();
        }

        return $this->processorCount;
    }

    /**
     * nproc, then getconf, then /proc/cpuinfo (like the shell script), otherwise 1.
     *
     * @throws \IPE\Exception\IO
     *
     * @return positive-int
     */
    private function detectProcessorCount()
    {
        foreach ([['nproc', []], ['getconf', ['_NPROCESSORS_ONLN']]] as list($command, $arguments)) {
            if ($this->fs->resolveCommand($command) === '') {
                continue;
            }
            list($rc, $output) = $this->fs->run(Command::create($command, $arguments)->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
            $matches = null;
            if ($rc === 0 && preg_match('/^\s*(?<count>[1-9]\d*)\s*$/D', $output, $matches)) {
                return max(1, (int) $matches['count']);
            }
        }
        if ($this->fs->isFile('/proc/cpuinfo')) {
            $count = preg_match_all('/^processor\s*:\s*\d+\s*$/m', $this->fs->readFile('/proc/cpuinfo'));
            if ($count > 0) {
                return $count;
            }
        }

        return 1;
    }

    /**
     * Look for a tool next to the PHP binary first, then in the PATH.
     * If the name of the PHP binary has a version suffix (like php8.4 or php84), we first look for the tool with the same suffix (like phpize8.4 or phpize84).
     *
     * @param non-empty-string $name
     * @param \Closure(non-empty-string): bool $isValid
     *
     * @throws \IPE\Exception\IO
     *
     * @return string the path of the first valid tool found (empty string if none)
     */
    private function findTool($name, \Closure $isValid)
    {
        $phpDir = dirname($this->php->getPHPBin());
        $candidateNames = [];
        $suffix = $this->getPHPBinSuffix();
        if ($suffix !== '') {
            $candidateNames[] = $name . $suffix;
        }
        $candidateNames[] = $name;
        $checked = [];
        foreach ($candidateNames as $candidateName) {
            foreach ([true, false] as $nextToPHP) {
                if ($nextToPHP) {
                    $candidate = $phpDir . $this->fs->getDirectorySeparator() . $candidateName;
                    if (!$this->fs->isFile($candidate)) {
                        continue;
                    }
                } else {
                    $candidate = $this->fs->resolveCommand($candidateName);
                    if ($candidate === '') {
                        continue;
                    }
                }
                if (isset($checked[$candidate])) {
                    continue;
                }
                $checked[$candidate] = true;
                if ($isValid($candidate)) {
                    return $candidate;
                }
                $this->logger->debug("Skipping {$candidate} since it does not match PHP {$this->php->getVersion()} (API {$this->php->getAPIVersion()})");
            }
        }

        return '';
    }

    /**
     * @return string
     *
     * @example '8.4' for /usr/bin/php8.4 (Debian, Ubuntu)
     * @example '84' for /usr/bin/php84 (Alpine)
     * @example '' for /usr/local/bin/php
     */
    private function getPHPBinSuffix()
    {
        $matches = null;

        return preg_match('/^php(?<suffix>\d[\w.-]*)$/D', basename($this->php->getPHPBin()), $matches) ? $matches['suffix'] : '';
    }
}
