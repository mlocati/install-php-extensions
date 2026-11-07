<?php

namespace IPE\Extension;

/**
 * Interface for extensions that need additional flags for the C preprocessor (CPPFLAGS), such as include directories.
 */
interface ProvidesPreprocessorFlags
{
    /**
     * @return string empty string if none
     */
    public function getPreprocessorFlags(BuildContext $context);
}
