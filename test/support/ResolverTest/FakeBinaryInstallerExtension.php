<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\PHPInfo;

/**
 * An extension whose archive contains an installer provided by the vendor.
 */
final class FakeBinaryInstallerExtension extends Extension implements Extension\ProvidesBinaryInstaller
{
    /**
     * @var bool
     */
    private $canRunInstaller;

    /**
     * @param bool $canRunInstaller
     */
    public function __construct(PHPInfo $php, OS $os, $canRunInstaller)
    {
        parent::__construct($php, $os);
        $this->canRunInstaller = $canRunInstaller;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return 'fakeinstaller';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getSupportedPHPVersions()
     */
    public static function getSupportedPHPVersions()
    {
        return ['8.3'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['fakeinstaller'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsBinary::getBinaryArchiveURL()
     */
    public function getBinaryArchiveURL($versionRequirements)
    {
        return 'https://example.com/dist/binary.tar.gz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryInstaller::canRunBinaryInstaller()
     */
    public function canRunBinaryInstaller($rootDir)
    {
        return $this->canRunInstaller;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryInstaller::runBinaryInstaller()
     */
    public function runBinaryInstaller($rootDir, Option\Values $options)
    {
    }
}
