<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;
use IPE\OS;
use IPE\PHPInfo;

final class FakeThreadSafetyExtension extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresThreadSafetySupport
{
    /**
     * @var bool
     */
    private $supportsZTS;

    /**
     * @var bool
     */
    private $supportsNTS;

    /**
     * @param bool $supportsZTS
     * @param bool $supportsNTS
     */
    public function __construct(PHPInfo $php, OS $os, $supportsZTS, $supportsNTS)
    {
        parent::__construct($php, $os);
        $this->supportsZTS = $supportsZTS;
        $this->supportsNTS = $supportsNTS;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return 'fakethreadsafety';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getSupportedPHPVersions()
     */
    public static function getSupportedPHPVersions()
    {
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5', '8.6'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['fakethreadsafety'];
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

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresThreadSafetySupport::supportsZTS()
     */
    public function supportsZTS()
    {
        return $this->supportsZTS;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresThreadSafetySupport::supportsNTS()
     */
    public function supportsNTS()
    {
        return $this->supportsNTS;
    }
}
