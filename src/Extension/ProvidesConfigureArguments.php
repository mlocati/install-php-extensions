<?php

namespace IPE\Extension;

/**
 * Interface for extensions that must always be configured with specific arguments.
 */
interface ProvidesConfigureArguments
{
    /**
     * Get the arguments for the configure script, just before compiling the extension (so the extensions installed before it in the same run are known): the ones specified by the user follow them, so they can override them.
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedOS
     *
     * @return list<non-empty-string>
     */
    public function getConfigureArguments(BuildContext $context);
}
