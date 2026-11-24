<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;

/**
 * An extension available only in binary form, also identified by the name of a Composer package.
 */
class FakeIdentifiedBinaryExtension extends FakeBinaryExtension implements Extension\IdentifiedByComposerPackage
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\IdentifiedByComposerPackage::getIdentifyingComposerPackageName()
     */
    public static function getIdentifyingComposerPackageName()
    {
        return 'fake/ext-binary';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\IdentifiedByComposerPackage::getExtensionVersionFromComposerVersion()
     */
    public function getExtensionVersionFromComposerVersion($composerVersion)
    {
        $matches = null;

        return preg_match('/^v(\d+\.\d+\.\d+)(\.\d+)?$/D', $composerVersion, $matches) ? $matches[1] : '';
    }
}
