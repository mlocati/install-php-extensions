<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\SystemDependencies;

final class memprof extends Extension implements
    Extension\AvailableAsComposerPackage,
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\DeclaresThreadSafetySupport,
    Extension\DeclaresUnsupportedEnvironments,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'memprof';

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
     * @see \IPE\Extension\DeclaresUnsupportedEnvironments::getUnsupportedEnvironments()
     */
    public static function getUnsupportedEnvironments()
    {
        return [
            'alpine3.0..3.8' => "this system doesn't provide the Judy library",
            'alpine3.9..3.15' => 'the extension can\'t be compiled with the compiler or the libraries of this system',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['memprof'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsComposerPackage::getComposerPackageName()
     */
    public static function getComposerPackageName()
    {
        return 'arnaud-lb/memprof';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'memprof';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Benchmarking';
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
            return '1.0.0';
        }

        return $phpVersion <= 700 ? '2.1.0' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\libjudy::HANDLE, SystemDependencies\bsdcompatheaders::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresThreadSafetySupport::supportsZTS()
     */
    public function supportsZTS()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresThreadSafetySupport::supportsNTS()
     */
    public function supportsNTS()
    {
        return true;
    }
}
