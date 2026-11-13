<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

final class ssh2 extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'ssh2';

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
        return ['ssh2'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'ssh2';
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
        $phpVersion = $this->php->getMajorMinorVersion();
        if ($phpVersion <= 506) {
            return '0.13';
        }
        if ($phpVersion <= 704) {
            // Newer versions require libssh2 1.6, but these systems provide libssh2 1.4.3 or 1.5.0
            if (
                $this->os instanceof OS\Debian && $this->os->getNumericVersion() <= OS\Debian::JESSIE
                || $this->os instanceof OS\Ubuntu && $this->os->getNumericVersion() <= OS\Ubuntu::XENIAL
            ) {
                return '1.3.1';
            }

            return '1.4.1';
        }
        // Newer versions require libssh2 1.11, but these systems provide libssh2 1.8, 1.9 or 1.10
        if (
            $this->os instanceof OS\Alpine && $this->os->getNumericVersion() <= 315
            || $this->os instanceof OS\Debian && $this->os->getNumericVersion() <= OS\Debian::BOOKWORM
            || $this->os instanceof OS\Ubuntu && $this->os->getNumericVersion() <= OS\Ubuntu::LUNAR
            || $this->os instanceof OS\Posix\RHELLike\EnterpriseLinux && $this->os->getNumericVersion() <= 8
            || $this->os instanceof OS\AmazonLinux && $this->os->getNumericVersion() <= 2023
            || $this->os instanceof OS\Fedora && $this->os->getNumericVersion() <= 38
        ) {
            return '1.5.0';
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
        return [SystemDependencies\libssh2::HANDLE];
    }
}
