<?php

namespace IPE\Extensions;

use IPE\Exception;
use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\Service\JSON;
use IPE\SystemDependencies\binutils;

/**
 * @see https://support.tideways.com/documentation/setup/installation/php-extension.html
 */
final class tideways extends Extension\PrecompiledExtension implements Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'tideways';

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
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return [
            'tideways',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        // The module is published with its debug symbols
        return [binutils::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsBinary::getBinaryArchiveURL()
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\IO\NotFound
     * @throws \IPE\Exception\UnsupportedExtension
     * @throws \IPE\Exception\UnsupportedOS
     */
    public function getBinaryArchiveURL($versionRequirements)
    {
        $this->checkNoVersion($versionRequirements);
        // The module uses zend_weakrefs_hash_add(), available since PHP 8.0.13
        if ($this->php->getMajorMinorVersion() === 800 && version_compare($this->php->getBaseVersion(), '8.0.13') < 0) {
            throw new Exception\IO\NotFound("Tideways doesn't provide a binary for PHP 8.0 before 8.0.13");
        }
        if ($this->os instanceof OS\Alpine) {
            $platform = 'linux-alpine';
        } elseif ($this->os instanceof OS\Debian) {
            $platform = 'linux';
        } else {
            throw new Exception\UnsupportedOS("Tideways can't be installed on {$this->os->getDisplayName()}");
        }
        $architecture = OS\Architecture::getVendorName($this->php, $this->os, [
            OS\Architecture::X64 => 'amd64',
            OS\Architecture::ARM64 => 'arm64',
        ]);
        try {
            $links = JSON::decode($this->downloader->getContent('https://app.tideways.io/api/current-versions'), true);
        } catch (Exception\JSON $x) {
            throw new Exception\IO("Failed to decode the list of the Tideways versions:\n{$x->getMessage()}");
        }
        foreach (['php', 'links', $platform] as $key) {
            $links = is_array($links) && isset($links[$key]) ? $links[$key] : null;
        }
        if (!is_array($links)) {
            throw new Exception\IO("Failed to find the URLs of Tideways for {$platform}");
        }
        $url = isset($links[$architecture]) ? $links[$architecture] : null;
        if (!is_string($url) || $url === '') {
            throw new Exception\IO\NotFound("Tideways doesn't provide a binary for {$platform} on {$architecture}");
        }

        return $url;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryModule::findBinaryModule()
     */
    public function findBinaryModule($rootDir, $versionRequirements)
    {
        $fs = $this->os->getFilesystem();
        $separator = $fs->getDirectorySeparator();
        $directory = $fs->isDirectory("{$rootDir}{$separator}dist") ? "{$rootDir}{$separator}dist" : $rootDir;
        $module = "{$directory}{$separator}tideways-php" . ($this->os instanceof OS\Alpine ? '-alpine' : '') . '-' . self::getPHPMajorDotMinor($this->php) . ($this->php->isThreadSafe() ? '-zts' : '') . '.so';

        return $fs->isFile($module) ? $module : '';
    }
}
