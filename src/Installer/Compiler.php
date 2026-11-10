<?php

namespace IPE\Installer;

use IPE\Exception;
use IPE\Extension;
use IPE\Extension\Resolved;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\PHPInfo;

/**
 * Compiles the source code of a resolved extension and installs the module in the PHP extension directory.
 */
final class Compiler
{
    /**
     * Warnings that only add noise to the logs, for both C and C++.
     *
     * @var string
     */
    const WARNING_FLAGS = '-Wno-unused-variable -Wno-unused-but-set-variable';

    /**
     * Warnings that only add noise to the logs, only for C.
     *
     * @var string
     */
    const C_WARNING_FLAGS = '-Wno-incompatible-pointer-types -Wno-int-conversion -Wno-implicit-int -Wno-return-mismatch -Wno-declaration-missing-parameter-type -Wno-discarded-qualifiers';

    /**
     * Warnings that only add noise to the logs, only for C++.
     *
     * @var string
     */
    const CXX_WARNING_FLAGS = '';

    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var \IPE\Installer\BuildTools
     */
    private $buildTools;

    /**
     * @var \IPE\Installer\BuildOptions
     */
    private $buildOptions;

    /**
     * @var \IPE\PHPInfo
     */
    private $php;

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var \IPE\Installer\ZendExtensionDetector
     */
    private $zendExtensionDetector;

    public function __construct(BuildTools $buildTools, BuildOptions $buildOptions, PHPInfo $php, Filesystem $fs, ZendExtensionDetector $zendExtensionDetector, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->buildTools = $buildTools;
        $this->buildOptions = $buildOptions;
        $this->php = $php;
        $this->fs = $fs;
        $this->zendExtensionDetector = $zendExtensionDetector;
    }

    /**
     * Run phpize, configure, make and make install in the source directory of the extension.
     *
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Installer\CompiledExtension
     */
    public function compile(Resolved $resolved)
    {
        if ($this->fs->getDirectorySeparator() !== '/') {
            throw new Exception\UnsupportedOS('Compiling extensions is only supported on POSIX-like systems');
        }
        $known = $resolved->getKnownExtension();
        $sourceDir = $this->ensureConfigM4($resolved->getPath());
        $context = new Extension\BuildContext($sourceDir, $resolved->getVersion(), $resolved->getOptionValues(), $this->buildOptions);
        if ($known instanceof Extension\PatchesSourceCode) {
            $known->patchSourceCode($context);
        }
        $phpize = $this->buildTools->getPhpize();
        if ($this->fs->isFile("{$sourceDir}/configure")) {
            $this->logger->info('Cleaning up a previous build');
            $this->runStep('phpize --clean', $phpize, ['--clean'], $sourceDir);
        }
        $this->logger->info('Running phpize');
        $this->runStep('phpize', $phpize, [], $sourceDir);
        if ($known instanceof Extension\PatchesConfigureScript) {
            $known->patchConfigureScript($context);
        }
        $configureArguments = $this->buildConfigureArguments($known, $context);
        $this->logger->info('Running configure' . ($configureArguments === [] ? '' : ' with ' . implode(' ', $configureArguments)));
        $this->runStep('configure', './configure', $configureArguments, $sourceDir, $this->buildConfigureEnvironment($known, $context));
        $make = $this->buildTools->getMake();
        $jobs = $this->buildTools->getParallelJobs($this->buildOptions);
        $makeEnvironment = $this->buildMakeEnvironment($known, $context);
        $this->logger->info("Running make (with {$jobs} parallel jobs)");
        $this->runStep('make', $make, ["-j{$jobs}"], $sourceDir, $makeEnvironment);
        $moduleName = $this->detectModuleName($resolved, "{$sourceDir}/modules");
        $this->logger->info('Running make install');
        $this->runStep('make install', $make, ["-j{$jobs}", 'install'], $sourceDir, $makeEnvironment);
        $modulePath = "{$this->php->getExtensionDir()}/{$moduleName}.so";
        if (!$this->fs->isFile($modulePath)) {
            throw new Exception\IO("make install did not create the module file {$modulePath}");
        }
        $manifest = $resolved->getManifest();
        $zendExtension = $manifest === null ? $this->zendExtensionDetector->isZendExtension($modulePath) : $manifest->isZendExtension();
        if ($this->buildOptions->getType() === BuildOptions::TYPE_OPTIMIZED) {
            $this->stripSymbols($modulePath, $sourceDir);
        }

        return new CompiledExtension($resolved, $moduleName, $modulePath, $zendExtension, $known instanceof Extension\ProvidesIniSettings ? $known->getIniSettings() : '', $configureArguments);
    }

    /**
     * Some extensions ship config0.m4 or config9.m4 instead of config.m4 (phpize needs the latter), and some keep them in a subdirectory.
     *
     * @param non-empty-string $sourceDir
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string the directory containing config.m4
     */
    private function ensureConfigM4($sourceDir)
    {
        if ($this->prepareConfigM4In($sourceDir)) {
            return $sourceDir;
        }
        foreach ($this->fs->listDirectoryContents($sourceDir) as $item) {
            $subdirectory = $sourceDir . '/' . basename($item);
            if ($this->fs->isDirectory($subdirectory) && $this->prepareConfigM4In($subdirectory)) {
                return $subdirectory;
            }
        }
        throw new Exception\IO("The directory {$sourceDir} does not contain a config.m4 file: it does not look like the source code of a PHP extension");
    }

    /**
     * @param non-empty-string $directory
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    private function prepareConfigM4In($directory)
    {
        if ($this->fs->isFile("{$directory}/config.m4")) {
            return true;
        }
        foreach (['config0.m4', 'config9.m4'] as $alternative) {
            if ($this->fs->isFile("{$directory}/{$alternative}")) {
                $this->logger->debug("Renaming {$alternative} to config.m4");
                $this->fs->rename("{$directory}/{$alternative}", "{$directory}/config.m4");

                return true;
            }
        }

        return false;
    }

    /**
     * The php-config and libdir of the running PHP, unless the extension or the user specified them, plus the arguments of the extension and of the user.
     *
     * @param \IPE\Extension|null $known
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedOS
     *
     * @return list<non-empty-string>
     */
    private function buildConfigureArguments($known, Extension\BuildContext $context)
    {
        $values = $context->getOptionValues();
        if ($known instanceof Extension\ProvidesConfigureArguments) {
            $values = $values->withConfigureArgumentsBefore($known->getConfigureArguments($context));
        }
        $arguments = [];
        $phpConfig = $this->buildTools->getPhpConfig();
        if ($phpConfig !== '') {
            $arguments[] = "--with-php-config={$phpConfig}";
        }
        $libdir = $this->buildTools->getLibdir();
        if ($libdir !== '') {
            $arguments[] = "--with-libdir={$libdir}";
        }

        return $values->withConfigureArgumentsBefore($arguments)->getConfigureArguments();
    }

    /**
     * Debian's gcc doesn't enable the stack protector by default.
     * Setting these variables replaces the default of configure (-g -O2).
     *
     * @param \IPE\Extension|null $known
     *
     * @throws \IPE\Exception\IO
     *
     * @return array<non-empty-string, string>
     */
    private function buildConfigureEnvironment($known, Extension\BuildContext $context)
    {
        $flags = ($this->buildOptions->getType() === BuildOptions::TYPE_DEBUG ? '-O0 -g' : '-g -O2') . ' ' . $this->buildTools->getStackProtectorFlag();
        $result = self::mergeFlags(
            ['CFLAGS' => "{$flags} " . self::WARNING_FLAGS . ' ' . self::C_WARNING_FLAGS, 'CXXFLAGS' => rtrim("{$flags} " . self::WARNING_FLAGS . ' ' . self::CXX_WARNING_FLAGS)],
            $known instanceof Extension\ProvidesConfigureEnvironment ? $known->getConfigureEnvironment($context) : []
        );
        $result += $this->buildTools->getConfigureEnvironment();
        if ($this->php->getMajorMinorVersion() < 700) {
            $includeDir = $this->buildTools->getIncludeDir();
            if ($includeDir !== '' && $this->fs->isFile("{$includeDir}/ext/pdo/php_pdo_driver.h")) {
                // PHP 5 looks for the PDO headers only in <prefix>/include/php, but Debian and Ubuntu packages put them in /usr/include/php/<API version>
                $result['pdo_cv_inc_path'] = "{$includeDir}/ext";
            }
        }

        return $result;
    }

    /**
     * With GCC < 14 calling an undeclared function (for example, one removed in newer PHP versions) is just a warning: the module builds, but it fails at runtime.
     * PHP uses EXTRA_CFLAGS and EXTRA_CXXFLAGS only to compile the sources of the extension (not in the configure checks).
     *
     * @param \IPE\Extension|null $known
     *
     * @return array<non-empty-string, string>
     */
    private function buildMakeEnvironment($known, Extension\BuildContext $context)
    {
        return self::mergeFlags(
            ['EXTRA_CFLAGS' => '-Werror=implicit-function-declaration'],
            $known instanceof Extension\ProvidesMakeEnvironment ? $known->getMakeEnvironment($context) : []
        );
    }

    /**
     * The flags of the extension come after the ones of IPE, and the ones of the user come last so that they win.
     *
     * @param array<non-empty-string, string> $ipeFlags
     * @param array<non-empty-string, string> $extensionFlags
     *
     * @return array<non-empty-string, string> without empty values
     */
    private static function mergeFlags(array $ipeFlags, array $extensionFlags)
    {
        foreach ($extensionFlags as $name => $value) {
            $ipeFlags[$name] = isset($ipeFlags[$name]) ? trim("{$ipeFlags[$name]} {$value}") : $value;
        }
        $result = [];
        foreach ($ipeFlags as $name => $value) {
            $userFlags = getenv($name);
            if ($userFlags !== false && $userFlags !== '') {
                $value = trim("{$value} {$userFlags}");
            }
            if ($value !== '') {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    /**
     * @param non-empty-string $description
     * @param non-empty-string $command
     * @param list<string> $arguments
     * @param non-empty-string $workingDirectory
     * @param array<non-empty-string, string> $environment
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function runStep($description, $command, array $arguments, $workingDirectory, array $environment = [])
    {
        list($rc) = $this->fs->run(Command::create($command, $arguments)->withWorkingDirectory($workingDirectory)->withEnvironment($environment)->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\IO("{$description} failed with exit code {$rc}");
        }
    }

    /**
     * Remove the debug symbols from the module, to reduce its size.
     *
     * @param non-empty-string $modulePath
     * @param non-empty-string $sourceDir
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function stripSymbols($modulePath, $sourceDir)
    {
        if ($this->buildTools->getStrip() === '') {
            $this->logger->warning('The strip command is not available: the debug symbols will be kept in the module');

            return;
        }
        $this->buildTools->stripModule($modulePath, $sourceDir);
    }

    /**
     * Find the module file created by make: the only .so file, or the one named after the extension.
     *
     * @param non-empty-string $modulesDir
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string the module name, without the .so extension
     */
    private function detectModuleName(Resolved $resolved, $modulesDir)
    {
        $moduleNames = [];
        if ($this->fs->isDirectory($modulesDir)) {
            foreach ($this->fs->listDirectoryContents($modulesDir) as $item) {
                $matches = null;
                if (preg_match('/^(?<name>.+)\.so$/D', basename($item), $matches)) {
                    $moduleNames[] = $matches['name'];
                }
            }
        }
        if ($moduleNames === []) {
            throw new Exception\IO("make did not create any module file in {$modulesDir}");
        }
        if (count($moduleNames) === 1) {
            return $moduleNames[0];
        }
        $expectedNames = [];
        $known = $resolved->getKnownExtension();
        if ($known !== null) {
            $expectedNames = $known::getInstalledNames();
        }
        $manifest = $resolved->getManifest();
        if ($manifest !== null && $manifest->getExtensionName() !== '') {
            $expectedNames[] = $manifest->getExtensionName();
        }
        foreach ($expectedNames as $expectedName) {
            foreach ($moduleNames as $moduleName) {
                if (strcasecmp($moduleName, $expectedName) === 0) {
                    return $moduleName;
                }
            }
        }
        throw new Exception\IO('Unable to determine which module to install among: ' . implode(', ', $moduleNames));
    }
}
