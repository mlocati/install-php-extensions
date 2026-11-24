<?php

namespace IPE\Extension;

/**
 * Interface for extensions published as Composer packages that can't be used to install them (for example because they download the binaries of the vendor): the name of the package is an alias of the handle of the extension.
 * The versions of the package are used to resolve the version requirements that aren't exact versions.
 */
interface IdentifiedByComposerPackage
{
    /**
     * Get the name of the Composer package.
     *
     * @return non-empty-string
     *
     * @example 'cachewerk/ext-relay'
     */
    public static function getIdentifyingComposerPackageName();

    /**
     * Get the version of the extension that corresponds to a version of the Composer package.
     *
     * @param non-empty-string $composerVersion the version as published in the Composer repository
     *
     * @return string empty string if the version of the package doesn't correspond to a version of the extension
     *
     * @example 'v1.2.3' => '1.2.3'
     */
    public function getExtensionVersionFromComposerVersion($composerVersion);
}
