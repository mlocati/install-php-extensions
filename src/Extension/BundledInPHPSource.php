<?php

namespace IPE\Extension;

/**
 * Interface for extensions that may come bundled in PHP source code.
 *
 * @see https://github.com/php/php-src/tree/php-8.4.14/ext
 */
interface BundledInPHPSource
{
    /**
     * The vendor of the Composer package names of the bundled extensions (for example php/bcmath).
     *
     * @var string
     */
    const COMPOSER_VENDOR = 'php';

    /**
     * Get the name of the directory inside /ext of the PHP source code.
     *
     * @return non-empty-string
     *
     * @example 'bcmath'
     */
    public static function getPHPSourceExtDirectoryName();

    /**
     * Get the PHP versions that bundle this extension, as a composer version constraint.
     *
     * @return non-empty-string
     *
     * @example '*' for extensions bundled in every PHP version
     * @example '<7.2' for extensions bundled only in PHP versions before 7.2
     */
    public static function getBundledPHPVersions();
}
