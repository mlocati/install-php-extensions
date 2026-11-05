<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;

/**
 * An extension published as the Composer package apcu/apcu and on PECL (as mcrypt), that can't be installed from some versions.
 */
final class FakeRestrictedVersionsExtension extends Extension implements
    Extension\AvailableAsComposerPackage,
    Extension\AvailableOnPECL,
    Extension\RestrictsVersions
{
    /**
     * @var (callable(string, string): bool)|null receives the origin and the version (null to allow every version)
     */
    public static $restriction;

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return 'fakerestrictedversions';
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
        return ['fakerestrictedversions'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsComposerPackage::getComposerPackageName()
     */
    public static function getComposerPackageName()
    {
        return 'apcu/apcu';
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
     * @see \IPE\Extension\RestrictsVersions::canUseVersion()
     */
    public function canUseVersion($origin, $version)
    {
        return self::$restriction === null || call_user_func(self::$restriction, $origin, $version);
    }
}
