<?php

namespace IPE\Extension;

/**
 * Interface for extensions that need other PHP extensions (in addition to the ones declared in the extension manifest).
 */
interface DependsOnExtensions
{
    /**
     * Get the handles of the extensions that must be installed before this one: they are installed automatically if they are missing.
     *
     * @param string $version the extension version (empty string if unknown)
     *
     * @return list<non-empty-string>
     */
    public function getRequiredExtensions($version);

    /**
     * Get the handles of the extensions that this one uses if available: they are not installed automatically, but if they are going to be installed they will be installed first.
     *
     * @param string $version the extension version (empty string if unknown)
     *
     * @return list<non-empty-string>
     */
    public function getOptionalExtensions($version);
}
