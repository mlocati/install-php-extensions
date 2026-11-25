<?php

namespace IPE\OS;

use IPE\OS;

/**
 * An interface that POSIX-like systems (Linux, BSD, ...) must implement.
 */
interface Posix extends OS
{
    /**
     * Get the repository engine that provides system packages.
     *
     * @return \IPE\SystemPackager
     */
    public function getSystemPackager();
}
