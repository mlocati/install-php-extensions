<?php

namespace IPE\Extension;

use IPE\Option;

/**
 * Interface for extensions that need system dependencies (see the classes in the IPE\SystemDependencies namespace).
 */
interface RequiresSystemDependencies
{
    /**
     * Get the handles of the required system dependencies (in addition to the libraries declared in the extension manifest).
     *
     * @param string $version the extension version (empty string if unknown)
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedOS
     *
     * @return list<non-empty-string>
     */
    public function getRequiredSystemDependencies($version, Option\Values $options);
}
