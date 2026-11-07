<?php

namespace IPE\Extensions;

use IPE\Exception;
use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

/**
 * @see https://relay.so/builds
 */
final class relay extends Extension\PrecompiledExtension implements
    Extension\DeclaresUnsupportedEnvironments,
    Extension\DependsOnExtensions,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'relay';

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
        return ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresUnsupportedEnvironments::getUnsupportedEnvironments()
     */
    public static function getUnsupportedEnvironments()
    {
        $noBuild = 'the vendor doesn\'t publish ZTS builds for this system';
        $noHiredis = 'the ZTS build requires libhiredis 1.1.0, which is not available';

        return [
            'alpine3.10..3.16-zts' => $noBuild,
            'alpine3.17-zts' => 'the ZTS build requires the ck library, which is not available',
            'alpine3.22..3.24-zts' => $noHiredis,
            'debian10..11-zts' => $noBuild,
            'debian12-zts' => $noHiredis,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return [
            'relay',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getRequiredExtensions()
     */
    public function getRequiredExtensions($version)
    {
        return [msgpack::HANDLE, igbinary::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getOptionalExtensions()
     */
    public function getOptionalExtensions($version)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        // The module is published with its debug symbols
        $result = [SystemDependencies\binutils::HANDLE];
        // The builds for Alpine link to these libraries
        if ($this->os instanceof OS\Alpine) {
            $result[] = SystemDependencies\liblz4::HANDLE;
            $result[] = SystemDependencies\libzstd::HANDLE;
            if ($this->os->getNumericVersion() >= 317) {
                $result[] = SystemDependencies\libopenssl::HANDLE;
            }
        }
        // The ZTS builds link to these libraries
        if ($this->php->isThreadSafe()) {
            $result[] = SystemDependencies\libhiredis::HANDLE;
            $result[] = SystemDependencies\libck::HANDLE;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsBinary::getBinaryArchiveURL()
     */
    public function getBinaryArchiveURL($versionRequirements)
    {
        $this->checkExactVersion($versionRequirements);
        if ($this->os instanceof OS\Alpine) {
            $build = $this->os->getNumericVersion() < 317 ? 'alpine3.9' : 'alpine3.17';
        } elseif ($this->os instanceof OS\Debian) {
            $build = 'debian' . ($this->os->getNumericVersion() >= OS\Debian::VERSION_BOOKWORM ? '+libssl3' : '');
        } elseif ($this->os instanceof OS\Ubuntu) {
            $build = 'debian' . ($this->os->getNumericVersion() >= OS\Ubuntu::JAMMY ? '+libssl3' : '');
        } else {
            throw new Exception\UnsupportedOS("Relay can't be installed on {$this->os->getDisplayName()}");
        }
        $version = $versionRequirements === '' ? ltrim(trim($this->downloader->getContent('https://builds.r2.relay.so/meta/latest')), 'v') : $versionRequirements;
        $architecture = OS\Architecture::getVendorName($this->php, $this->os, [
            OS\Architecture::X64 => 'x86-64',
            OS\Architecture::ARM64 => 'aarch64',
        ]);
        list($distro, $flags) = explode('+', "{$build}+");
        $flags = $flags === '' ? '' : "+{$flags}";

        return "https://builds.r2.relay.so/v{$version}/relay-v{$version}-php" . self::getPHPMajorDotMinor($this->php) . "-{$distro}-{$architecture}{$flags}.tar.gz";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryModule::findBinaryModule()
     */
    public function findBinaryModule($rootDir, $versionRequirements)
    {
        $fs = $this->os->getFilesystem();
        $module = $rootDir . $fs->getDirectorySeparator() . ($this->php->isThreadSafe() ? 'relay-zts.so' : 'relay-pkg.so');

        return $fs->isFile($module) ? $module : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PrecompiledExtension::postProcessBinaryModule()
     */
    public function postProcessBinaryModule($modulePath)
    {
        $fs = $this->os->getFilesystem();
        // Every installation must have its own ID: it's stored in the module as a placeholder
        $uuid = trim($fs->readFile('/proc/sys/kernel/random/uuid'));
        $fs->writeFile($modulePath, str_replace('00000000-0000-0000-0000-000000000000', $uuid, $fs->readFile($modulePath)));
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PrecompiledExtension::getBinaryIniSettings()
     */
    public function getBinaryIniSettings(Extension\BuildContext $context)
    {
        $fs = $this->os->getFilesystem();
        $file = $context->getSourceDirectory() . $fs->getDirectorySeparator() . 'relay.ini';

        return $fs->isFile($file) ? (string) preg_replace('/^[ \t]*extension[ \t]*=.*$\r?\n?/m', '', $fs->readFile($file)) : '';
    }
}
