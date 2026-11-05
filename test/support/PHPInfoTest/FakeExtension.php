<?php

namespace IPE\Test\Support\PHPInfoTest;

use IPE\Extension;
use IPE\OS;
use IPE\PHPInfo;

class FakeExtension extends Extension
{
    /**
     * It's static like getInstalledNames(): the value is the one of the last created instance.
     *
     * @var non-empty-array<non-empty-string>
     */
    private static $installedNames = ['fake'];

    /**
     * @param non-empty-array<non-empty-string> $installedNames
     */
    public function __construct(PHPInfo $php, OS $os, array $installedNames)
    {
        parent::__construct($php, $os);
        self::$installedNames = $installedNames;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return 'fake';
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
        return self::$installedNames;
    }
}
