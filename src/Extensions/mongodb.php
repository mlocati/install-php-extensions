<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\SystemDependencies;

final class mongodb extends Extension implements
    Extension\AvailableAsComposerPackage,
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\ProvidesConfigureArguments,
    Extension\RequiresSystemDependencies,
    Extension\RestrictsVersions
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'mongodb';

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
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5', '8.6'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['mongodb'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsComposerPackage::getComposerPackageName()
     */
    public static function getComposerPackageName()
    {
        return 'mongodb/mongodb-extension';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'mongodb';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Database';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        $phpVersion = $this->php->getMajorMinorVersion();
        if ($phpVersion <= 505) {
            return '1.5.5';
        }
        if ($phpVersion <= 506) {
            return '1.7.5';
        }
        if ($phpVersion <= 700) {
            return '1.9.2';
        }
        if ($phpVersion <= 701) {
            return '1.11.1';
        }
        if ($phpVersion <= 703) {
            return '1.16.2';
        }

        return $phpVersion <= 800 ? '1.20.1' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RestrictsVersions::canUseVersion()
     */
    public function canUseVersion($origin, $version)
    {
        // Older Composer versions don't declare a pre-packaged source, and the GitHub zipball doesn't contain the git submodules
        return $origin !== Extension\Resolved::ORIGIN_COMPOSER || version_compare($version, '1.21.0') >= 0;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        $result = [SystemDependencies\libicu::HANDLE, SystemDependencies\libopenssl::HANDLE, SystemDependencies\libsasl::HANDLE, SystemDependencies\libsnappy::HANDLE, SystemDependencies\libzlib::HANDLE];
        if ($this->php->getMajorMinorVersion() >= 704) {
            $result[] = SystemDependencies\libzstd::HANDLE;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        $version = $context->getVersion();
        if ($version !== '' && version_compare($version, '1.17.0') < 0) {
            return [];
        }

        return [
            '--with-mongodb-client-side-encryption=yes',
            '--with-mongodb-snappy=yes',
            '--with-mongodb-zlib=yes',
            '--with-mongodb-zstd=yes',
            '--with-mongodb-sasl=yes',
            '--with-mongodb-ssl=yes',
            '--enable-mongodb-crypto-system-profile=yes',
        ];
    }
}
