<?php

namespace IPE\OS;

use IPE\OS\Posix\RHELLike\EnterpriseLinux;

/**
 * CentOS Stream.
 */
class CentOS extends EnterpriseLinux
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'centos';
    }
}
