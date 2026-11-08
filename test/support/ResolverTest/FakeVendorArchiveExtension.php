<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\PHPInfo;

/**
 * An extension to be compiled from an archive of its vendor.
 */
final class FakeVendorArchiveExtension extends Extension implements
    Extension\CompiledFromVendorArchive,
    Extension\DeclaresOptions
{
    /**
     * @var non-empty-string
     */
    private $url;

    /**
     * @param non-empty-string $url
     */
    public function __construct(PHPInfo $php, OS $os, $url = 'https://example.com/dist/foo-bar-1.2.3.zip')
    {
        parent::__construct($php, $os);
        $this->url = $url;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return 'fakevendor';
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
        return ['fakevendor'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresOptions::getOptions()
     */
    public static function getOptions()
    {
        return [
            new Option\CustomOption('variant', '', true, 'default'),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::getVendorArchiveVersion()
     */
    public function getVendorArchiveVersion($versionRequirements, Option\Values $options)
    {
        return $versionRequirements === '' ? '1.2.3' : $versionRequirements;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::getVendorArchiveURL()
     */
    public function getVendorArchiveURL($version, Option\Values $options)
    {
        return $this->url;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::prepareVendorSource()
     */
    public function prepareVendorSource(Extension\BuildContext $context)
    {
        return "{$context->getSourceDirectory()}/src";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::getVendorConfigureArguments()
     */
    public function getVendorConfigureArguments(Extension\BuildContext $context)
    {
        return ["--with-lib={$context->getSourceDirectory()}/lib"];
    }
}
