<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

final class redis extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\DependsOnExtensions,
    Extension\ProvidesConfigureArguments,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'redis';

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
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['redis'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'redis';
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
        if ($phpVersion <= 506) {
            return '4.3.0';
        }
        if ($phpVersion <= 701) {
            return '5.3.7';
        }

        return $phpVersion <= 703 ? '6.0.2' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getRequiredExtensions()
     */
    public function getRequiredExtensions($version)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getOptionalExtensions()
     */
    public function getOptionalExtensions($version)
    {
        return [igbinary::HANDLE, msgpack::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        $phpVersion = $this->php->getMajorMinorVersion();
        $result = [];
        if ($phpVersion >= 700 && $this->systemProvidesZstd()) {
            $result[] = SystemDependencies\libzstd::HANDLE;
        }
        if ($phpVersion >= 702) {
            $result[] = SystemDependencies\liblz4::HANDLE;
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
        $installed = $this->php->getInstalledExtensions();
        $result = ['--enable-redis-lzf'];
        if (in_array(igbinary::HANDLE, $installed, true)) {
            $result[] = '--enable-redis-igbinary';
        }
        if ($version !== '' && version_compare($version, '5.0.0') < 0) {
            return $result;
        }
        if ($this->systemProvidesZstd()) {
            $result[] = '--enable-redis-zstd';
        }
        if ($version !== '' && version_compare($version, '6.0.0') < 0) {
            return $result;
        }
        if (in_array(msgpack::HANDLE, $installed, true)) {
            $result[] = '--enable-redis-msgpack';
        }
        if ($this->php->getMajorMinorVersion() >= 702) {
            $result[] = '--enable-redis-lz4';
            $result[] = '--with-liblz4';
        }

        return $result;
    }

    /**
     * Old operating systems don't provide libzstd, or they provide a version older than 1.3.0.
     *
     * @return bool
     */
    private function systemProvidesZstd()
    {
        if ($this->os instanceof OS\Debian) {
            return $this->os->getNumericVersion() > OS\Debian::STRETCH;
        }
        if ($this->os instanceof OS\Ubuntu) {
            return $this->os->getNumericVersion() > OS\Ubuntu::XENIAL;
        }

        return true;
    }
}
