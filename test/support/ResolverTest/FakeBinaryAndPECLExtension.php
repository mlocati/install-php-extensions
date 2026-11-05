<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;

/**
 * An extension available on PECL (as mcrypt) and in binary form.
 */
class FakeBinaryAndPECLExtension extends FakeBinaryExtension implements Extension\AvailableOnPECL
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return 'fakebinarypecl';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'mcrypt';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Encryption';
    }
}
