<?php

namespace IPE\Extensions;

use IPE\Extension;

final class pdo_sqlsrv extends Extension\MicrosoftSQLServer
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'pdo_sqlsrv';

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return self::HANDLE;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getSupportedPHPVersions()
     */
    public static function getSupportedPHPVersions()
    {
        return ['7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }
}
