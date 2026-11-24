<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;

/**
 * An extension to be compiled from an archive of its vendor, also identified by the name of a Composer package.
 */
final class FakeIdentifiedVendorArchiveExtension extends FakeVendorArchiveExtension implements Extension\IdentifiedByComposerPackage
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\IdentifiedByComposerPackage::getIdentifyingComposerPackageName()
     */
    public static function getIdentifyingComposerPackageName()
    {
        return 'fake/ext-vendor';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\IdentifiedByComposerPackage::getExtensionVersionFromComposerVersion()
     */
    public function getExtensionVersionFromComposerVersion($composerVersion)
    {
        return preg_match('/^\d+\.\d+\.\d+$/D', $composerVersion) ? $composerVersion : '';
    }
}
