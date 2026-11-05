<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;

/**
 * An extension that declares an exact version: a beta release whose manifest doesn't support PHP < 7.2.
 */
final class FakeForcedVersionExtension extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return 'fakeforcedversion';
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
        return ['fakeforcedversion'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'mcrypt';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Encryption';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        return '1.0.8';
    }
}
