<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;
use IPE\Filesystem\Downloader;
use IPE\OS;
use IPE\PHPInfo;

/**
 * An extension available only in binary form.
 */
class FakeBinaryExtension extends Extension\PrecompiledExtension
{
    /**
     * @var non-empty-string
     */
    private $moduleFilename;

    /**
     * @var non-empty-string
     */
    private $url;

    /**
     * @param non-empty-string $moduleFilename
     * @param non-empty-string $url
     */
    public function __construct(PHPInfo $php, OS $os, Downloader $downloader, $moduleFilename, $url = 'https://example.com/dist/binary.tar.gz')
    {
        parent::__construct($php, $os, $downloader);
        $this->moduleFilename = $moduleFilename;
        $this->url = $url;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return 'fakebinary';
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
        return ['fakebinary'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsBinary::getBinaryArchiveURL()
     */
    public function getBinaryArchiveURL($versionRequirements)
    {
        return $this->url;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryModule::findBinaryModule()
     */
    public function findBinaryModule($rootDir, $versionRequirements)
    {
        $module = $rootDir . $this->os->getFilesystem()->getDirectorySeparator() . $this->moduleFilename;

        return $this->os->getFilesystem()->isFile($module) ? $module : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PrecompiledExtension::getBinaryIniSettings()
     */
    public function getBinaryIniSettings(Extension\BuildContext $context)
    {
        return "fakebinary.setting=1\n";
    }
}
