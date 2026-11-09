<?php

namespace IPE\Extensions;

use IPE\Exception;
use IPE\Extension;
use IPE\OS;

/**
 * @see https://docs.blackfire.io/up-and-running/installation
 */
final class blackfire extends Extension\PrecompiledExtension implements
    Extension\DeclaresUnsupportedEnvironments,
    Extension\DependsOnExtensions,
    Extension\ForbidsAccessToMissingBinaries
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'blackfire';

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
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5', '8.6'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return [
            'blackfire',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresUnsupportedEnvironments::getUnsupportedEnvironments()
     */
    public static function getUnsupportedEnvironments()
    {
        // The probe uses __b64_pton without linking libresolv, and with glibc 2.34+ PHP doesn't load it
        $reason = 'the probe for PHP 5 requires glibc 2.33 or older';

        return [
            'debian12..15-php5' => $reason,
            'ubuntu22.04..26.10-php5' => $reason,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getRequiredExtensions()
     */
    public function getRequiredExtensions($version)
    {
        // The probe uses the symbols of the session extension
        return [session::HANDLE];
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
     * @see \IPE\Extension\AvailableAsBinary::getBinaryArchiveURL()
     */
    public function getBinaryArchiveURL($versionRequirements)
    {
        $this->checkExactVersion($versionRequirements);
        if ($this->os instanceof OS\Alpine) {
            $distro = 'alpine';
        } elseif ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            $distro = 'linux';
        } else {
            throw new Exception\UnsupportedOS("The Blackfire probe can't be installed on {$this->os->getDisplayName()}");
        }
        $architecture = $this->getVendorArchitecture();
        $phpTag = str_replace('.', '', self::getPHPMajorDotMinor($this->php)) . ($this->php->isThreadSafe() ? '-zts' : '');
        if ($versionRequirements === '') {
            return "https://blackfire.io/api/v1/releases/probe/php/{$distro}/{$architecture}/{$phpTag}";
        }

        return "https://packages.blackfire.io/binaries/blackfire-php/{$versionRequirements}/blackfire-php-{$distro}_{$architecture}-php-{$phpTag}.tar.gz";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryModule::findBinaryModule()
     */
    public function findBinaryModule($rootDir, $versionRequirements)
    {
        $fs = $this->os->getFilesystem();
        // The file name contains the PHP API version
        foreach ($fs->listDirectoryContents($rootDir) as $item) {
            if (preg_match('/^blackfire-.+\.so$/D', basename($item))) {
                return $rootDir . $fs->getDirectorySeparator() . basename($item);
            }
        }

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PrecompiledExtension::getBinaryIniSettings()
     */
    public function getBinaryIniSettings(Extension\BuildContext $context)
    {
        return "blackfire.agent_socket=tcp://blackfire:8307\n";
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    private function getVendorArchitecture()
    {
        return OS\Architecture::getVendorName($this->php, $this->os, [
            OS\Architecture::X86 => 'i386',
            OS\Architecture::X64 => 'amd64',
            OS\Architecture::ARM64 => 'arm64',
        ]);
    }
}
