<?php

namespace IPE\Extension;

/**
 * Interface for extensions that need additional flags for the C and C++ compilers.
 */
interface ProvidesCompilerFlags
{
    /**
     * @return string an empty string if no flags are needed
     */
    public function getCompilerFlags(BuildContext $context);
}
