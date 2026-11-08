<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

final class grpc extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\ProvidesCompilerFlags,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'grpc';

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
        return ['grpc'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'grpc';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Networking';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        if ($this->php->getMajorMinorVersion() <= 506) {
            return '1.33.1';
        }
        if ($this->os instanceof OS\Debian) {
            $osVersion = $this->os->getNumericVersion();
            if ($osVersion <= OS\Debian::VERSION_JESSIE) {
                return '1.46.3';
            }
            if ($osVersion <= OS\Debian::VERSION_STRETCH) {
                // Newer versions require GCC 7
                return '1.52.1';
            }

            // Newer versions require GCC 10
            return $osVersion <= OS\Debian::VERSION_BUSTER ? '1.84.0' : '';
        }
        if ($this->os instanceof OS\Alpine) {
            $osVersion = $this->os->getNumericVersion();
            if ($osVersion <= 308) {
                // Newer versions require GCC 7
                return '1.52.1';
            }
            if ($osVersion <= 310) {
                // Newer versions require GCC 10
                return '1.84.0';
            }
            if ($osVersion <= 312) {
                // Newer versions crash the compiler
                return '1.80.0';
            }
            if ($osVersion <= 313) {
                // Newer versions crash the compiler
                return '1.70.0';
            }
        }

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\libstdcxx::HANDLE, SystemDependencies\libzlib::HANDLE, SystemDependencies\linuxheaders::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesCompilerFlags::getCompilerFlags()
     */
    public function getCompilerFlags(Extension\BuildContext $context)
    {
        return '-Wno-maybe-uninitialized -Wno-attributes -Wno-return-type';
    }
}
