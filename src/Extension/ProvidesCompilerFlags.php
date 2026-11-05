<?php

namespace IPE\Extension;

/**
 * Interface for extensions that need additional flags for the C and C++ compilers.
 */
interface ProvidesCompilerFlags
{
    /**
     * @return non-empty-string
     */
    public function getCompilerFlags();
}
