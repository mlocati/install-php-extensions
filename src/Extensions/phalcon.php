<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\OS;
use IPE\SystemPackager;

final class phalcon extends Extension implements
    Extension\AvailableAsComposerPackage,
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\DependsOnExtensions,
    Extension\ProvidesCompilerFlags,
    Extension\ProvidesSystemPackageNames
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'phalcon';

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
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['phalcon'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsComposerPackage::getComposerPackageName()
     */
    public static function getComposerPackageName()
    {
        return 'phalcon/cphalcon';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'phalcon';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'System';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        if (version_compare($this->php->getBaseVersion(), '7.4.1') < 0) {
            return '4.1.2';
        }
        $phpVersion = $this->php->getMajorMinorVersion();
        if ($phpVersion < 800) {
            return '5.4.0';
        }

        return $phpVersion < 801 ? '5.10.0' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getRequiredExtensions()
     */
    public function getRequiredExtensions($version)
    {
        // Besides PDO, phalcon implements SessionHandlerInterface: without session it can't be loaded
        $result = [pdo::HANDLE, session::HANDLE];
        if (!self::isVersionAtLeast($version, '5.0.0')) {
            $result[] = psr::HANDLE;
        }

        return $result;
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
     * @see \IPE\Extension\ProvidesCompilerFlags::getCompilerFlags()
     */
    public function getCompilerFlags(Extension\BuildContext $context)
    {
        return '-Wno-incompatible-pointer-types';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesSystemPackageNames::getSystemPackageNames()
     */
    public function getSystemPackageNames(SystemPackager\Package $phpPackage)
    {
        if (!$this->os instanceof OS\Posix || !$this->os->getSystemPackager() instanceof SystemPackager\APT) {
            return null;
        }
        $source = $phpPackage->getSource();

        // The repositories of Ondrej Sury include the major version of Phalcon in the names of the packages (for example, php7.4-phalcon5), and phpX.Y-phalcon is a dummy package with another version
        return ["{$source}-phalcon5", "{$source}-phalcon4", "{$source}-phalcon3", "{$source}-phalcon"];
    }
}
