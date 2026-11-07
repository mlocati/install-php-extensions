<?php

namespace IPE\Extensions;

use IPE\Exception;
use IPE\Extension;
use IPE\Filesystem\Command;
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
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\libcassandra::HANDLE];
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
    public function buildModule($rootDir, Option\Values $options, BuildTools $buildTools, $parallelJobs)
    {
        if ($this->usesScyllaDBDriver()) {
            $this->buildScyllaDBDriver($rootDir, $buildTools, $parallelJobs);
        } else {
            $this->buildNanoDriver("{$rootDir}/ext", $buildTools, $parallelJobs);
        }

        return "{$this->php->getExtensionDir()}/cassandra.so";
    }

    /**
     * The ScyllaDB driver requires PHP 8.3 and cmake 3.30.
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
            return $this->os->getNumericVersion() >= OS\Debian::VERSION_TRIXIE;
        }
        if ($this->os instanceof OS\Ubuntu) {
            return $this->os->getNumericVersion() >= OS\Ubuntu::RESOLUTE;
        }

        return false;
    }

    /**
     * @param non-empty-string $directory
     * @param positive-int $parallelJobs
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function buildScyllaDBDriver($directory, BuildTools $buildTools, $parallelJobs)
    {
        $arguments = ['-B', 'cmake-build', '-DCMAKE_BUILD_TYPE=Release', '-DCUSTOM_PHP_CONFIG=' . $buildTools->getPhpConfig(), '-DPHP_SCYLLADB_BACKEND=cassandra'];
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
        $this->runStep('./configure', [], $directory);
        $this->runStep($buildTools->getMake(), ['-j' . $parallelJobs, 'install'], $directory);
    }

    /**
     * @param non-empty-string $command
     * @param list<string> $arguments
     * @param non-empty-string $directory
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function runStep($command, array $arguments, $directory)
    {
        list($rc) = $this->os->getFilesystem()->run(Command::create($command, $arguments)->withWorkingDirectory($directory)->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\IO("Failed to compile the cassandra extension: {$command} failed with exit code {$rc}");
        }
    }
}
