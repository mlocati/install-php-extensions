<?php

namespace IPE\OS;

use IPE\OS\Posix\RHELLike\EnterpriseLinux;

/**
 * Red Hat Enterprise Linux (including the Universal Base Images).
 */
class RHEL extends EnterpriseLinux
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'rhel';
    }
}
