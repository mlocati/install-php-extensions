<?php

namespace IPE\Extensions;

use IPE\Extension;

final class psr extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'psr';

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
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5', '8.6'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['psr'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'psr';
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
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        $phpVersion = $this->php->getMajorMinorVersion();
        if ($phpVersion <= 505) {
            return '0.5.1';
        }
        if ($phpVersion <= 506) {
            return '0.6.1';
        }

        return $phpVersion <= 702 ? '1.1.0' : '';
    }
}
