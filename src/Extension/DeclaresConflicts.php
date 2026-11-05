<?php

namespace IPE\Extension;

/**
 * Interface for extensions that can't be loaded together with other extensions.
 * What's declared here wins over what's declared in the manifest of the extension.
 */
interface DeclaresConflicts
{
    /**
     * @param string $version
     *
     * @return list<non-empty-string> the names of the extensions as seen by PHP
     */
    public function getConflictingExtensions($version);
}
