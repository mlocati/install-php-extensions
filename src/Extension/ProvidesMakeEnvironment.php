<?php

namespace IPE\Extension;

/**
 * Interface for extensions that need variables passed to make, for example EXTRA_CFLAGS and EXTRA_CXXFLAGS because their config.m4 overwrites CFLAGS or CXXFLAGS.
 * These flags come after the ones of config.m4, and they aren't used by the configure checks.
 */
interface ProvidesMakeEnvironment
{
    /**
     * @return array<non-empty-string, string> the values are appended to the ones set by IPE and by the user, so they should be lists of flags
     */
    public function getMakeEnvironment(BuildContext $context);
}
