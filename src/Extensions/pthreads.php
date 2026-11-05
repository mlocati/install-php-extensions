<?php

namespace IPE\Extensions;

use IPE\Extension;

final class pthreads extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\DeclaresThreadSafetySupport
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'pthreads';

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
        return ['5.5', '5.6', '7.0'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['pthreads'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'pthreads';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'System';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresThreadSafetySupport::supportsZTS()
     */
    public function supportsZTS()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresThreadSafetySupport::supportsNTS()
     */
    public function supportsNTS()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        return $this->php->getMajorMinorVersion() <= 506 ? '2.0.10' : '';
    }
}
