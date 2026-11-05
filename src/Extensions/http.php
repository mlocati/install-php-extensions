<?php

namespace IPE\Extensions;

use IPE\Exception;
use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

final class http extends Extension implements
    Extension\AvailableAsComposerPackage,
    Extension\AvailableOnPECL,
    Extension\DeclaresConflicts,
    Extension\DeclaresDefaultVersion,
    Extension\DeclaresPriority,
    Extension\DependsOnExtensions,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'http';

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
        return [
            'http',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsComposerPackage::getComposerPackageName()
     */
    public static function getComposerPackageName()
    {
        return 'm6w6/ext-http';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'pecl_http';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'HTTP';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresConflicts::getConflictingExtensions()
     */
    public function getConflictingExtensions($version)
    {
        // PHP crashes at shutdown in raphf if parallel started a thread
        return [parallel::HANDLE];
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
            return '2.6.0';
        }

        return $phpVersion <= 704 ? '3.2.4' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getRequiredExtensions()
     */
    public function getRequiredExtensions($version)
    {
        return $this->php->getMajorMinorVersion() <= 704 ? [propro::HANDLE, raphf::HANDLE] : [raphf::HANDLE];
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
        // The versions of the extension for PHP 5 don't use ICU and idnkit
        $legacy = $this->php->getMajorMinorVersion() <= 506;
        if ($this->os instanceof OS\Alpine) {
            // curl-dev provides the SSL headers
            return $legacy
                ? [SystemDependencies\libcurl::HANDLE, SystemDependencies\libevent::HANDLE, SystemDependencies\libidn::HANDLE, SystemDependencies\libzlib::HANDLE]
                : [SystemDependencies\libcurl::HANDLE, SystemDependencies\libevent::HANDLE, SystemDependencies\libicu::HANDLE, SystemDependencies\libidn::HANDLE, SystemDependencies\libidnkit::HANDLE, SystemDependencies\libzlib::HANDLE];
        }
        if ($this->os instanceof OS\Debian) {
            return $legacy
                ? [SystemDependencies\libcurl::HANDLE, SystemDependencies\libevent::HANDLE, SystemDependencies\libidn::HANDLE, SystemDependencies\libopenssl::HANDLE, SystemDependencies\libzlib::HANDLE]
                : [SystemDependencies\libcurl::HANDLE, SystemDependencies\libevent::HANDLE, SystemDependencies\libgcrypt::HANDLE, SystemDependencies\libicu::HANDLE, SystemDependencies\libidn2::HANDLE, SystemDependencies\libidnkit::HANDLE, SystemDependencies\libopenssl::HANDLE, SystemDependencies\libzlib::HANDLE];
        }

        throw new Exception\UnsupportedOS('The system libraries required by ' . self::getHandle() . " are not known for {$this->os->getDisplayName()}");
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresPriority::getPriority()
     */
    public static function getPriority()
    {
        return 90;
    }
}
