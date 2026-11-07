<?php

namespace IPE\Extensions;

use IPE\Exception;
use IPE\Extension;
use IPE\OS;

/**
 * @see https://www.ioncube.com/loaders.php
 */
final class ioncube_loader extends Extension\PrecompiledExtension implements Extension\DeclaresDefaultVersion
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'ioncube_loader';

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
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return [
            'ionCube Loader',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        // Version 14.4.0 gives a segmentation fault there
        return $this->os instanceof OS\Alpine && $this->os->getNumericVersion() <= 312 ? '14.0.0' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsBinary::getBinaryArchiveURL()
     */
    public function getBinaryArchiveURL($versionRequirements)
    {
        $this->checkExactVersion($versionRequirements);
        $suffix = $versionRequirements === '' ? '' : "_{$versionRequirements}";

        return 'https://downloads.ioncube.com/loader_downloads/ioncube_loaders_' . self::getPlatform($this->os, $versionRequirements) . '_' . $this->getVendorArchitecture() . "{$suffix}.tar.gz";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryModule::findBinaryModule()
     */
    public function findBinaryModule($rootDir, $versionRequirements)
    {
        $fs = $this->os->getFilesystem();
        $module = $rootDir . $fs->getDirectorySeparator() . 'ioncube_loader_' . self::getPlatform($this->os, $versionRequirements) . '_' . self::getPHPMajorDotMinor($this->php) . ($this->php->isThreadSafe() ? '_ts' : '') . '.so';

        return $fs->isFile($module) ? $module : '';
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

    /**
     * @param string $version empty string for the latest version
     *
     * @throws \IPE\Exception\UnsupportedOS
     *
     * @return non-empty-string
     */
    private static function getPlatform(OS $os, $version)
    {
        if ($os instanceof OS\Alpine) {
            // The builds for musl are available since version 14.4.0
            return $version === '' || version_compare($version, '14.4.0') >= 0 ? 'lin-musl' : 'lin';
        }
        if ($os instanceof OS\Debian || $os instanceof OS\Ubuntu) {
            return 'lin';
        }

        throw new Exception\UnsupportedOS("ionCube Loader can't be installed on {$os->getDisplayName()}");
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    private function getVendorArchitecture()
    {
        return OS\Architecture::getVendorName($this->php, $this->os, [
            OS\Architecture::X86 => 'x86',
            OS\Architecture::X64 => 'x86-64',
            OS\Architecture::ARM32 => 'armv7l',
            OS\Architecture::ARM64 => 'aarch64',
        ]);
    }
}
