<?php

namespace IPE\Extensions;

use IPE\Extension;

final class seaslog extends Extension implements
    Extension\AvailableOnPECL,
    Extension\ProvidesCompilerFlags
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'seaslog';

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
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['seaslog'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'seaslog';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Logging';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesCompilerFlags::getCompilerFlags()
     */
    public function getCompilerFlags()
    {
        return '-Wno-incompatible-pointer-types';
    }
}
