<?php

namespace IPE\Extensions;

use IPE\Exception;
use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies\libeudev;

/**
 * @see https://www.sourceguardian.com/loaders.html
 */
final class sourceguardian extends Extension\PrecompiledExtension implements Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'sourceguardian';

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
            'SourceGuardian',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [libeudev::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsBinary::getBinaryArchiveURL()
     */
    public function getBinaryArchiveURL($versionRequirements)
    {
        $this->checkNoVersion($versionRequirements);
        if (!$this->os instanceof OS\Posix) {
            throw new Exception\UnsupportedOS("SourceGuardian can't be installed on {$this->os->getDisplayName()}");
        }
        $architecture = OS\Architecture::getVendorName($this->php, $this->os, [
            OS\Architecture::X86 => 'i386',
            OS\Architecture::X64 => 'x86_64',
            OS\Architecture::ARM64 => 'aarch64',
        ]);

        return "https://www.sourceguardian.com/loaders/download/loaders.linux-{$architecture}.tar.gz";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryModule::findBinaryModule()
     */
    public function findBinaryModule($rootDir, $versionRequirements)
    {
        $fs = $this->os->getFilesystem();
        $suffix = ($this->php->isThreadSafe() ? 'ts' : '') . '.lin';
        // Some PHP versions have a loader for a specific patch version
        foreach ([$this->php->getBaseVersion(), self::getPHPMajorDotMinor($this->php)] as $version) {
            $module = $rootDir . $fs->getDirectorySeparator() . "ixed.{$version}{$suffix}";
            if ($fs->isFile($module)) {
                return $module;
            }
        }

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PrecompiledExtension::isZendExtension()
     */
    public function isZendExtension()
    {
        return true;
    }
}
