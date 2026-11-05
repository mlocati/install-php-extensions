<?php

namespace IPE\Extension;

/**
 * Interface for extensions available on https://pecl.php.net/.
 */
interface AvailableOnPECL
{
    /**
     * Get the handle of the extension as published on PECL.
     *
     * @return string
     *
     * @example 'xdebug'
     */
    public static function getPECLHandle();

    /**
     * Get the category of the extension on PECL: it tells where to find the requirements of all its versions.
     *
     * @return non-empty-string
     *
     * @example 'Tools and Utilities'
     */
    public static function getPECLCategory();
}
