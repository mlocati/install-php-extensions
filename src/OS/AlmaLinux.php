<?php

namespace IPE\OS;

use IPE\OS\Posix\RHELLike\EnterpriseLinux;

class AlmaLinux extends EnterpriseLinux
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'almalinux';
    }
}
