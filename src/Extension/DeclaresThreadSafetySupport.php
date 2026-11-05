<?php

namespace IPE\Extension;

/**
 * Interface for extensions that don't work with both the ThreadSafe and the non-ThreadSafe builds of PHP.
 * What's declared here wins over what's declared in the manifest of the extension.
 */
interface DeclaresThreadSafetySupport
{
    /**
     * @return bool
     */
    public function supportsZTS();

    /**
     * @return bool
     */
    public function supportsNTS();
}
