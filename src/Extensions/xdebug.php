<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

final class xdebug extends Extension implements
    Extension\AvailableAsComposerPackage,
    Extension\AvailableOnPECL,
    Extension\DeclaresConflicts,
    Extension\DeclaresDefaultVersion,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'xdebug';

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
        return ['xdebug'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsComposerPackage::getComposerPackageName()
     */
    public static function getComposerPackageName()
    {
        return 'xdebug/xdebug';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'xdebug';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'PHP';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresConflicts::getConflictingExtensions()
     */
    public function getConflictingExtensions($version)
    {
        // ddtrace disables itself with any Xdebug on PHP 7.0, and with Xdebug < 2.9.5 on newer PHP versions
        if ($this->php->getMajorMinorVersion() <= 700 || ($version !== '' && version_compare($version, '2.9.5') < 0)) {
            return [ddtrace::HANDLE];
        }

        return [];
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
            return '2.5.5';
        }
        if ($phpVersion <= 700) {
            return '2.6.1';
        }
        if ($phpVersion <= 701) {
            return '2.9.8';
        }

        return $phpVersion <= 704 ? '3.1.6' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return $this->os instanceof OS\Alpine && $this->php->getMajorMinorVersion() >= 800 ? [SystemDependencies\linuxheaders::HANDLE] : [];
    }
}
