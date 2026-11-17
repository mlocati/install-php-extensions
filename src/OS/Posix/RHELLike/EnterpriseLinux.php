<?php

namespace IPE\OS\Posix\RHELLike;

use IPE\OS\Posix\RHELLike;

/**
 * Red Hat Enterprise Linux and the distributions compatible with it: the numeric version is the major version (eg 9 for 9.8).
 */
abstract class EnterpriseLinux extends RHELLike
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS\Posix\RHELLike::MINIMUM_VERSION
     */
    const MINIMUM_VERSION = 8;
}
