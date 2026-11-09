<?php

namespace IPE\Extension;

/**
 * Interface for extensions that need variables passed to configure, such as CFLAGS, CXXFLAGS or CPPFLAGS.
 * configure saves them in the Makefile, so they are used by the configure checks and when compiling.
 */
interface ProvidesConfigureEnvironment
{
    /**
     * @return array<non-empty-string, string> the values are appended to the ones set by IPE and by the user, so they should be lists of flags
     */
    public function getConfigureEnvironment(BuildContext $context);
}
