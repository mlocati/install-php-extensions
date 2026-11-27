<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

final class stomp extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\ProvidesConfigureArguments,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'stomp';

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
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.2', '8.3', '8.4'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['stomp'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'stomp';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Tools and Utilities';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        return $this->php->getMajorMinorVersion() <= 506 ? '1.0.9' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\libopenssl::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        if ($this->os instanceof OS\Alpine) {
            return ['--with-openssl-dir=/usr'];
        }
        // PHP_SETUP_OPENSSL uses pkg-config, which finds the keg-only openssl formula of macOS too
        if ($this->os instanceof OS\Posix\DebianLike || $this->os instanceof OS\Posix\RHELLike || $this->os instanceof OS\MacOS || $this->os instanceof OS\Wolfi) {
            return ['--with-openssl-dir=yes'];
        }

        throw $this->createUnknownOSException();
    }
}
