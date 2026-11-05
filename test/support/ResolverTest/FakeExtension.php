<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;
use IPE\Option;

/**
 * A bundled extension (reusing the mcrypt fixture directory) that declares its options.
 */
final class FakeExtension extends Extension implements
    Extension\BundledInPHPSource,
    Extension\DeclaresOptions
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return 'fakeext';
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
        return ['fakeext'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return 'mcrypt';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getBundledPHPVersions()
     */
    public static function getBundledPHPVersions()
    {
        return '*';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresOptions::getOptions()
     */
    public static function getOptions()
    {
        return [
            new Option\ConfigureOption('enable-fake-debug', 'Enable debugging', false),
            new Option\ConfigureOption('with-fake-dir', 'Path to the fake library', true),
            new Option\CustomOption('variant', 'The variant to be built (std or alt)', true, 'std'),
        ];
    }
}
