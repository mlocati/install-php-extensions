<?php

namespace IPE\Extension;

/**
 * Interface for extensions published as Composer packages (for example on https://packagist.org/extensions).
 */
interface AvailableAsComposerPackage
{
    /**
     * Get the name of the Composer package.
     *
     * @return string
     *
     * @example 'xdebug/xdebug'
     */
    public static function getComposerPackageName();
}
