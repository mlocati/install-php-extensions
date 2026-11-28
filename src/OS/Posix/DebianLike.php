<?php

namespace IPE\OS\Posix;

/**
 * The systems that use DEB packages and APT (Debian, Ubuntu).
 *
 * @method \IPE\SystemPackager\APT getSystemPackager()
 */
interface DebianLike extends WithOSRelease
{
    /**
     * @return string
     *
     * @example 'trixie'
     * @example 'noble'
     */
    public function getCodename();
}
