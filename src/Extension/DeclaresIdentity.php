<?php

namespace IPE\Extension;

/**
 * In PHP 5 a method of a class can't be both abstract and static without a strict standards error: an interface doesn't have this limit.
 */
interface DeclaresIdentity
{
    /**
     * Get the handle that uniquely identifies this extension for IPE.
     *
     * @return non-empty-string
     *
     * @example 'xdebug'
     */
    public static function getHandle();

    /**
     * Get the versions of PHP where this extension is supported by IPE.
     *
     * @return non-empty-list<non-empty-string>
     *
     * @example ['7.4', '8.0', '8.1']
     */
    public static function getSupportedPHPVersions();

    /**
     * Get all the possible names seen by PHP when this extension is installed.
     *
     * @return non-empty-array<non-empty-string>
     *
     * @example ['Xdebug']
     */
    public static function getInstalledNames();
}
