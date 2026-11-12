<?php

namespace IPE\OS;

use IPE\OS\Posix\RHELLike;

class Fedora extends RHELLike
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'fedora';
    }
}
