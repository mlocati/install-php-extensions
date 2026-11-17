<?php

namespace IPE\OS;

use IPE\OS\Posix\RHELLike\EnterpriseLinux;

class OracleLinux extends EnterpriseLinux
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'ol';
    }
}
