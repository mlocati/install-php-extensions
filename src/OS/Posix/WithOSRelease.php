<?php

namespace IPE\OS\Posix;

use IPE\OS\Posix;
use IPE\Service\Container;

/**
 * The systems detected by reading /etc/os-release.
 */
interface WithOSRelease extends Posix
{
    /**
     * @param array<non-empty-string, string> $data the contents of /etc/os-release
     *
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     *
     * @return static
     */
    public static function fromOSRelease(array $data, Container $app);
}
