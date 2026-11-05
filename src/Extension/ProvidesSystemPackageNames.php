<?php

namespace IPE\Extension;

use IPE\SystemPackager\Package;

/**
 * Interface for extensions whose system packages can't be found from their handle and from the names they are installed with.
 */
interface ProvidesSystemPackageNames
{
    /**
     * Get the names of the system packages that may provide the extension, in order of preference.
     *
     * @param \IPE\SystemPackager\Package $phpPackage the system package that installed PHP
     *
     * @return list<non-empty-string>|null NULL to look for the packages named after the extension, empty array if the extension must never be installed from system packages
     *
     * @example ['php8.4-phalcon', 'php8.4-phalcon5']
     */
    public function getSystemPackageNames(Package $phpPackage);
}
