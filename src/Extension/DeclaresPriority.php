<?php

namespace IPE\Extension;

/**
 * Interface for extensions that PHP must load before or after the other ones (for example because they use the symbols of the extensions they depend on).
 * The priority declared in the composer.json file of the extension takes precedence.
 */
interface DeclaresPriority
{
    /**
     * Get the priority of the extension (prefix of the ini file name: lower values are loaded first).
     *
     * @return int between 10 and 99 (the default is \IPE\Extension\Manifest::DEFAULT_PRIORITY)
     */
    public static function getPriority();
}
