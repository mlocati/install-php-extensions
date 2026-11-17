<?php

namespace IPE\OS;

use IPE\OS\Posix\RHELLike;

class AmazonLinux extends RHELLike
{
    /**
     * Amazon Linux 2 uses yum.
     *
     * {@inheritdoc}
     *
     * @see \IPE\OS\Posix\RHELLike::MINIMUM_VERSION
     */
    const MINIMUM_VERSION = 2023;

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'amzn';
    }
}
