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
     * @param \IPE\Option\Values|null $options null if the extension isn't built by us (so we don't know how it was configured)
     *
     * @return list<non-empty-string> the names of the extensions as seen by PHP
     */
    public function getConflictingExtensions($version, $options = null);
}
