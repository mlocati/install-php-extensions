<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;

/**
 * An extension published as the Composer package apcu/apcu, that requires a non-existing major version on PHP 8+.
 */
final class FakeDefaultVersionExtension extends Extension implements
    Extension\AvailableAsComposerPackage,
    Extension\DeclaresDefaultVersion
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return 'fakedefaultversion';
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
        return ['fakedefaultversion'];
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
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        return $this->php->getMajorMinorVersion() >= 800 ? '^9' : '';
    }
}
