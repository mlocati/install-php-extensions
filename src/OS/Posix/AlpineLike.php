<?php

namespace IPE\OS\Posix;

/**
 * The systems that use APK packages (Alpine, Wolfi).
 *
 * @method \IPE\SystemPackager\APK getSystemPackager()
 */
interface AlpineLike extends WithOSRelease
{
}
