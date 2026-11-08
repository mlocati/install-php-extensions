<?php

namespace IPE\Extensions;

use IPE\Exception;
use IPE\Extension;
use IPE\Filesystem\Command;
use IPE\Installer\BuildTools;
use IPE\Option;
use IPE\SystemDependencies;

/**
 * @see https://github.com/snowflakedb/pdo_snowflake
 */
final class pdo_snowflake extends Extension implements
    Extension\BuildsModule,
    Extension\DeclaresDefaultVersion,
    Extension\DeclaresUnsupportedEnvironments,
    Extension\ProvidesIniSettings,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'pdo_snowflake';

    /**
     * @public
     * @var string
     */
    const VERSION = '4.3.1';

    /**
     * @public
     * @var string
     */
    const CACERT_FILE = '/usr/local/share/snowflake/cacert.pem';

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
        return ['7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresUnsupportedEnvironments::getUnsupportedEnvironments()
     */
    public static function getUnsupportedEnvironments()
    {
        return [
            'alpine' => 'the vendor only supports glibc',
            'debian8' => 'the extension requires OpenSSL 1.1',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['pdo_snowflake'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        $phpVersion = $this->php->getMajorMinorVersion();
        if ($phpVersion <= 701) {
            return '0.2.4';
        }
        // On PHP 7.2, 1.0.0 doesn't link libuuid
        if ($phpVersion <= 704) {
            return '2.0.0';
        }
        if ($phpVersion <= 800) {
            return '2.0.3';
        }

        return $phpVersion <= 801 ? '3.7.0' : self::VERSION;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\cmake::HANDLE, SystemDependencies\libcurl::HANDLE, SystemDependencies\libopenssl::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsBinary::getBinaryArchiveURL()
     */
    public function getBinaryArchiveURL($versionRequirements)
    {
        if (!preg_match('/^\d+(\.\d+)*$/D', $versionRequirements)) {
            throw new Exception\UnsupportedExtension("The extension pdo_snowflake can only be installed by specifying an exact version number (you asked for '{$versionRequirements}')");
        }

        return "https://codeload.github.com/snowflakedb/pdo_snowflake/tar.gz/v{$versionRequirements}";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BuildsModule::buildModule()
     */
    public function buildModule(Extension\BuildContext $context, BuildTools $buildTools, $parallelJobs)
    {
        $fs = $this->os->getFilesystem();
        $rootDir = $context->getSourceDirectory();
        $environment = ['PHP_HOME' => dirname(dirname($buildTools->getPhpConfig()))];
        $version = $context->getVersion();
        // Before 0.2.5 the build script doesn't set PLATFORM (like scripts/_init.sh does), so it doesn't link the static libraries
        if ($version !== '' && version_compare($version, '0.2.5') < 0) {
            list($rc, $kernelName) = $fs->run(Command::create('uname')->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
            if ($rc !== 0) {
                throw new Exception\IO('Failed to detect the kernel name');
            }
            $environment['PLATFORM'] = strtolower(trim($kernelName));
        }
        $command = Command::create('bash', ['scripts/build_pdo_snowflake.sh'])
            ->withWorkingDirectory($rootDir)
            ->withEnvironment($environment)
            ->withOutput(Command::OUTPUT_SHOW)
        ;
        list($rc) = $fs->run($command);
        if ($rc !== 0) {
            throw new Exception\IO("Failed to compile the pdo_snowflake extension: the build script failed with exit code {$rc}");
        }
        $fs->createDirectory(dirname(self::CACERT_FILE));
        $fs->copyFile("{$rootDir}/libsnowflakeclient/cacert.pem", self::CACERT_FILE);

        return "{$rootDir}/modules/pdo_snowflake.so";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesIniSettings::getIniSettings()
     */
    public function getIniSettings()
    {
        return 'pdo_snowflake.cacert=' . self::CACERT_FILE . "\n";
    }
}
