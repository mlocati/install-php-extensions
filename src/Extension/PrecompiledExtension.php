<?php

namespace IPE\Extension;

use IPE\Exception;
use IPE\Extension;
use IPE\Filesystem\Downloader;
use IPE\OS;
use IPE\PHPInfo;

/**
 * What the extensions published as a binary module have in common.
 */
abstract class PrecompiledExtension extends Extension implements ProvidesBinaryModule
{
    /**
     * @var \IPE\Filesystem\Downloader
     */
    protected $downloader;

    public function __construct(PHPInfo $php, OS $os, Downloader $downloader)
    {
        parent::__construct($php, $os);
        $this->downloader = $downloader;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryModule::postProcessBinaryModule()
     */
    public function postProcessBinaryModule($modulePath)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryModule::getBinaryIniSettings()
     */
    public function getBinaryIniSettings(BuildContext $context)
    {
        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryModule::isZendExtension()
     */
    public function isZendExtension()
    {
        return false;
    }

    /**
     * Vendors let us download a specific version, but they don't let us list the available ones.
     *
     * @param string $versionRequirements
     *
     * @throws \IPE\Exception\UnsupportedExtension
     *
     * @return void
     */
    protected function checkExactVersion($versionRequirements)
    {
        if ($versionRequirements !== '' && !preg_match('/^\d+(\.\d+)*$/D', $versionRequirements)) {
            throw new Exception\UnsupportedExtension('The extension ' . static::getHandle() . " can only be installed by specifying an exact version number (you asked for '{$versionRequirements}')");
        }
    }

    /**
     * @param string $versionRequirements
     *
     * @throws \IPE\Exception\UnsupportedExtension
     *
     * @return void
     */
    protected function checkNoVersion($versionRequirements)
    {
        if ($versionRequirements !== '') {
            throw new Exception\UnsupportedExtension('The extension ' . static::getHandle() . " is available only in its latest version (you asked for '{$versionRequirements}')");
        }
    }

    /**
     * @return non-empty-string
     *
     * @example '8.4'
     */
    protected static function getPHPMajorDotMinor(PHPInfo $php)
    {
        return (int) floor($php->getMajorMinorVersion() / 100) . '.' . ($php->getMajorMinorVersion() % 100);
    }
}
