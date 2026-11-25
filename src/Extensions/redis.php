<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;
use IPE\SystemPackager;

final class redis extends Extension implements
    Extension\AvailableAsComposerPackage,
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\DependsOnExtensions,
    Extension\ProvidesConfigureArguments,
    Extension\ProvidesSystemPackageNames,
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
     * @see \IPE\Extension\AvailableAsComposerPackage::getComposerPackageName()
     */
    public static function getComposerPackageName()
    {
        return 'phpredis/phpredis';
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
        // redis provides a session handler: without session it can't be loaded
        return [session::HANDLE];
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
        if ($this->usesSystemLZF($version)) {
            $result[] = SystemDependencies\liblzf::HANDLE;
        }
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
     * @see \IPE\Extension\ProvidesSystemPackageNames::getSystemPackageNames()
     */
    public function getSystemPackageNames(SystemPackager\Package $phpPackage)
    {
        if ($this->os instanceof OS\MacOS) {
            return $this->os->getSystemPackager()->getExtensionFormulae($phpPackage, ['phpredis']);
        }

        return null;
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
        $result = [];
        if ($this->usesSystemLZF($version)) {
            $result[] = '--enable-redis-lzf';
            if ($this->os instanceof OS\MacOS) {
                // The formula has no pkg-config file, and config.m4 looks for the headers only in /usr/local and /usr
                $result[] = '--with-liblzf=' . $this->os->getSystemPackager()->getFormulaPrefix('liblzf');
            } else {
                $result[] = '--with-liblzf';
            }
        } elseif ($this->os->getFilesystem()->isFile($context->getSourceDirectory() . '/liblzf/lzf.h')) {
            // liblzf is a git submodule: the PECL archives include it, the GitHub archives (used by Packagist) don't
            $result[] = '--enable-redis-lzf';
        }
        if (in_array(igbinary::HANDLE, $installed, true)) {
            $result[] = '--enable-redis-igbinary';
        }
        if (!self::isVersionAtLeast($version, '5.0.0')) {
            return $result;
        }
        if ($this->systemProvidesZstd()) {
            $result[] = '--enable-redis-zstd';
        }
        if (!self::isVersionAtLeast($version, '6.0.0')) {
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
     * Before 5.3.0 the configure script doesn't use pkg-config, so it doesn't find the headers of Debian (in /usr/include/liblzf).
     *
     * @param string $version
     *
     * @return bool
     */
    private function usesSystemLZF($version)
    {
        return self::isVersionAtLeast($version, '5.3.0') && SystemDependencies\liblzf::isProvidedByOS($this->os);
    }

    /**
     * Old operating systems don't provide libzstd, or they provide a version older than 1.3.0.
     *
     * @throws \IPE\Exception\UnsupportedOS
     *
     * @return bool
     */
    private function systemProvidesZstd()
    {
        if ($this->os instanceof OS\Alpine) {
            return true;
        }
        if ($this->os instanceof OS\Debian) {
            return $this->os->getNumericVersion() > OS\Debian::STRETCH;
        }
        if ($this->os instanceof OS\Ubuntu) {
            return $this->os->getNumericVersion() > OS\Ubuntu::XENIAL;
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return true;
        }
        if ($this->os instanceof OS\MacOS) {
            return true;
        }

        throw $this->createUnknownOSException();
    }
}
