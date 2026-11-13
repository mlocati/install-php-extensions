<?php

namespace IPE\Extensions;

use IPE\Exception;
use IPE\Extension;
use IPE\Filesystem\Command;
use IPE\Installer\BuildOptions;
use IPE\Installer\BuildTools;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

/**
 * The driver on PECL is abandoned.
 *
 * @see https://github.com/he4rt/scylladb-php-driver
 * @see https://github.com/nano-interactive/ext-cassandra
 */
final class cassandra extends Extension implements
    Extension\BuildsModule,
    Extension\DeclaresUnsupportedEnvironments,
    Extension\DependsOnExtensions,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'cassandra';

    /**
     * @public
     * @var string
     */
    const SCYLLADB_VERSION = '1.5.1';

    /**
     * The last commit that can be built with phpize: the following ones evolved into the ScyllaDB driver.
     *
     * @public
     * @var string
     */
    const NANO_COMMIT = '1cf12c5ce49ed43a2c449bee4b7b23ce02a37bf0';

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return self::HANDLE;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getSupportedPHPVersions()
     */
    public static function getSupportedPHPVersions()
    {
        return ['7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresUnsupportedEnvironments::getUnsupportedEnvironments()
     */
    public static function getUnsupportedEnvironments()
    {
        return [
            'debian8..9' => "the extension can't be compiled with the compiler or the libraries of this system",
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['cassandra'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getRequiredExtensions()
     */
    public function getRequiredExtensions($version)
    {
        // The build scripts of the ScyllaDB driver use token_get_all()
        return $this->usesScyllaDBDriver() ? [tokenizer::HANDLE] : [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getOptionalExtensions()
     */
    public function getOptionalExtensions($version)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        if (!$this->usesScyllaDBDriver()) {
            return [SystemDependencies\libcassandra::HANDLE];
        }

        // gen_stub.php downloads PHP-Parser with wget or curl
        return [SystemDependencies\libcassandra::HANDLE, SystemDependencies\curl::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsBinary::getBinaryArchiveURL()
     */
    public function getBinaryArchiveURL($versionRequirements)
    {
        if ($this->usesScyllaDBDriver()) {
            if ($versionRequirements !== '' && !preg_match('/^\d+(\.\d+)*$/D', $versionRequirements)) {
                throw new Exception\UnsupportedExtension("The extension cassandra can only be installed by specifying an exact version number (you asked for '{$versionRequirements}')");
            }

            return 'https://github.com/he4rt/scylladb-php-driver/archive/refs/tags/v' . ($versionRequirements === '' ? self::SCYLLADB_VERSION : $versionRequirements) . '.tar.gz';
        }
        if ($versionRequirements !== '') {
            throw new Exception\UnsupportedExtension("It's not possible to choose the version of the extension cassandra in this environment (you asked for '{$versionRequirements}')");
        }

        return 'https://github.com/nano-interactive/ext-cassandra/tarball/' . self::NANO_COMMIT;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BuildsModule::buildModule()
     */
    public function buildModule(Extension\BuildContext $context, BuildTools $buildTools)
    {
        $rootDir = $context->getSourceDirectory();
        $parallelJobs = $buildTools->getParallelJobs($context->getBuildOptions());
        if ($this->usesScyllaDBDriver()) {
            $this->buildScyllaDBDriver($rootDir, $buildTools, $context->getBuildOptions()->getType(), $parallelJobs);
        } else {
            $this->buildNanoDriver("{$rootDir}/ext", $buildTools, $parallelJobs);
        }

        return "{$this->php->getExtensionDir()}/cassandra.so";
    }

    /**
     * The ScyllaDB driver requires PHP 8.3, cmake 3.30 and a C23 compiler (GCC 13).
     *
     * @return bool
     */
    private function usesScyllaDBDriver()
    {
        if ($this->php->getMajorMinorVersion() < 803) {
            return false;
        }

        if ($this->os instanceof OS\Alpine) {
            return $this->os->getNumericVersion() >= 321;
        }
        if ($this->os instanceof OS\Debian) {
            return $this->os->getNumericVersion() >= OS\Debian::TRIXIE;
        }
        if ($this->os instanceof OS\Ubuntu) {
            return $this->os->getNumericVersion() >= OS\Ubuntu::RESOLUTE;
        }
        if ($this->os instanceof OS\Posix\RHELLike\EnterpriseLinux) {
            return $this->os->getNumericVersion() >= 10;
        }
        if ($this->os instanceof OS\Fedora) {
            return true;
        }

        return false;
    }

    /**
     * @param non-empty-string $directory
     * @param string $buildType one of the \IPE\Installer\BuildOptions::TYPE_... constants
     * @param positive-int $parallelJobs
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function buildScyllaDBDriver($directory, BuildTools $buildTools, $buildType, $parallelJobs)
    {
        // FindPHP only looks for a command named php, which may be missing or another version (for example, with the php83 package of Alpine)
        $arguments = ['-B', 'cmake-build', '-DCMAKE_BUILD_TYPE=' . self::getCMakeBuildType($buildType), '-DCUSTOM_PHP_CONFIG=' . $buildTools->getPhpConfig(), '-DPHP_BINARY=' . $this->php->getPHPBin(), '-DPHP_SCYLLADB_BACKEND=cassandra'];
        // The default CPU type is x86-64-v2, which generates code that may not run on older CPUs
        $architecture = OS\Architecture::ofPHP($this->php, $this->os);
        if ($architecture === OS\Architecture::X64) {
            $arguments[] = '-DCPU_TYPE=x86-64';
        } elseif ($architecture === OS\Architecture::X86) {
            $arguments[] = '-DCPU_TYPE=i686';
        }
        $this->runStep('cmake', $arguments, $directory);
        // gen_stub.php downloads PHP-Parser on its first run: running it in parallel may fail
        $this->runStep('cmake', ['--build', 'cmake-build', '--target', 'generated-sources'], $directory);
        $this->runStep('cmake', ['--build', 'cmake-build', '--parallel', (string) $parallelJobs], $directory);
        $this->runStep('cmake', ['--install', 'cmake-build', '--component', 'extension'], $directory);
    }

    /**
     * @param string $buildType one of the \IPE\Installer\BuildOptions::TYPE_... constants
     *
     * @return non-empty-string
     */
    private static function getCMakeBuildType($buildType)
    {
        switch ($buildType) {
            case BuildOptions::TYPE_DEBUG:
                return 'Debug';
            case BuildOptions::TYPE_WITH_SYMBOLS:
                return 'RelWithDebInfo';
            default:
                return 'Release';
        }
    }

    /**
     * @param non-empty-string $directory
     * @param positive-int $parallelJobs
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function buildNanoDriver($directory, BuildTools $buildTools, $parallelJobs)
    {
        $this->runStep($buildTools->getPhpize(), [], $directory);
        $this->runStep('./configure', [], $directory, $buildTools->getConfigureEnvironment());
        $this->runStep($buildTools->getMake(), ['-j' . $parallelJobs, 'install'], $directory);
    }

    /**
     * @param non-empty-string $command
     * @param list<string> $arguments
     * @param non-empty-string $directory
     * @param array<non-empty-string, string> $environment
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function runStep($command, array $arguments, $directory, array $environment = [])
    {
        list($rc) = $this->os->getFilesystem()->run(Command::create($command, $arguments)->withWorkingDirectory($directory)->withEnvironment($environment)->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\IO("Failed to compile the cassandra extension: {$command} failed with exit code {$rc}");
        }
    }
}
