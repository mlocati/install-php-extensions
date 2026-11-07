<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

final class amqp extends Extension implements
    Extension\AvailableAsComposerPackage,
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\DeclaresUnsupportedEnvironments,
    Extension\ProvidesPreprocessorFlags,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'amqp';

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
            'ubuntu14.04' => 'the version of the librabbitmq library of this system is too old',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['amqp'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsComposerPackage::getComposerPackageName()
     */
    public static function getComposerPackageName()
    {
        return 'php-amqp/php-amqp';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'amqp';
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
        if ($this->os instanceof OS\Debian && $this->os->getNumericVersion() <= OS\Debian::JESSIE) {
            // This system provides librabbitmq 0.5.2
            return '1.9.3';
        }
        if ($this->os instanceof OS\Ubuntu && $this->os->getNumericVersion() <= OS\Ubuntu::XENIAL) {
            // Ubuntu 16.04 provides librabbitmq 0.7.1
            return '1.11.0';
        }
        $phpVersion = $this->php->getMajorMinorVersion();
        if ($phpVersion <= 505) {
            return '1.9.4';
        }

        return $phpVersion <= 703 ? '1.11.0' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\librabbitmq::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesPreprocessorFlags::getPreprocessorFlags()
     */
    public function getPreprocessorFlags(Extension\BuildContext $context)
    {
        // amqp uses strtoimax() without including inttypes.h, which the headers of PHP include only since PHP 5.6
        if ($this->php->getMajorMinorVersion() <= 505) {
            return '-include inttypes.h';
        }

        return '';
    }
}
