<?php

namespace IPE\OS;

use IPE\OS;
use IPE\Service\Container;

/**
 * An interface that POSIX-like systems (Linux, BSD, ...) must implement.
 */
interface Posix extends OS
{
    /**
     * @param array<non-empty-string, string> $data the contents of /etc/os-release
     *
     * @throws \IPE\Exception\UnsupportedOS
     *
     * @return static
     */
    public static function fromOSRelease(array $data, Container $app);

    /**
     * Get the repository engine that provides system packages.
     *
     * @return \IPE\SystemPackager
     */
    public function getSystemPackager();
}
