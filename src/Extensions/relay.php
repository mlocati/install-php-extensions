<?php

namespace IPE\Extensions;

use IPE\Architecture;
use IPE\Exception;
use IPE\Extension;
use IPE\Filesystem\Command;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

/**
 * @see https://relay.so/builds
 */
final class relay extends Extension\PrecompiledExtension implements
    Extension\DeclaresUnsupportedEnvironments,
    Extension\DependsOnExtensions,
    Extension\IdentifiedByComposerPackage,
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
        return ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5', '8.6'];
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
        $noCK = 'the ZTS build requires the ck library, which is not available';

        return [
            'alpine3.10..3.16-zts' => $noBuild,
            'alpine3.17-zts' => $noCK,
            'alpine3.22..3.24-zts' => $noHiredis,
            'debian10..11-zts' => $noBuild,
            'debian12-zts' => $noHiredis,
            'amzn2023-zts' => $noCK,
            'el8-zts' => $noBuild,
            'el9-zts' => $noHiredis,
            'fedora45..46' => 'the vendor builds require OpenSSL 3, but this system uses OpenSSL 4',
            'macos-zts' => $noBuild,
            'wolfilike' => "the vendor doesn't publish builds for this system",
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\IdentifiedByComposerPackage::getIdentifyingComposerPackageName()
     */
    public static function getIdentifyingComposerPackageName()
    {
        return 'cachewerk/ext-relay';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\IdentifiedByComposerPackage::getExtensionVersionFromComposerVersion()
     */
    public function getExtensionVersionFromComposerVersion($composerVersion)
    {
        $matches = null;

        // A fourth number (for example v0.50.2.1) marks a fix of the Composer package
        return preg_match('/^v?(\d+\.\d+\.\d+)(\.\d+)?$/D', $composerVersion, $matches) ? $matches[1] : '';
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
        $result = [session::HANDLE];
        if (!self::isVersionAtLeast($version, '0.30.0')) {
            $result[] = msgpack::HANDLE;
            $result[] = igbinary::HANDLE;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getOptionalExtensions()
     */
    public function getOptionalExtensions($version)
    {
        return self::isVersionAtLeast($version, '0.30.0') ? [msgpack::HANDLE, igbinary::HANDLE] : [];
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
        // The builds for Alpine, for the RHEL-like systems and for macOS link to these libraries
        if ($this->os instanceof OS\Alpine) {
            $result[] = SystemDependencies\liblz4::HANDLE;
            $result[] = SystemDependencies\libzstd::HANDLE;
            if ($this->os->getNumericVersion() >= 317) {
                $result[] = SystemDependencies\libopenssl::HANDLE;
            }
        } elseif ($this->os instanceof OS\Posix\RHELLike || $this->os instanceof OS\MacOS) {
            $result[] = SystemDependencies\libopenssl::HANDLE;
        } elseif (!$this->os instanceof OS\Posix\DebianLike) {
            throw $this->createUnknownOSException();
        }
        // The ZTS builds and the builds for macOS link to these libraries
        if ($this->php->isThreadSafe() || $this->os instanceof OS\MacOS) {
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
            $build = 'debian' . ($this->os->getNumericVersion() >= OS\Debian::BOOKWORM ? '+libssl3' : '');
        } elseif ($this->os instanceof OS\Ubuntu) {
            $build = 'debian' . ($this->os->getNumericVersion() >= OS\Ubuntu::JAMMY ? '+libssl3' : '');
        } elseif ($this->os instanceof OS\Posix\RHELLike\EnterpriseLinux) {
            $build = $this->os->getNumericVersion() <= 8 ? 'centos8' : "el{$this->os->getNumericVersion()}";
        } elseif ($this->os instanceof OS\AmazonLinux) {
            $build = 'el9';
        } elseif ($this->os instanceof OS\Fedora) {
            // The vendor doesn't publish builds for Fedora
            $build = 'el10';
        } elseif ($this->os instanceof OS\MacOS) {
            $build = 'darwin';
        } else {
            throw new Exception\UnsupportedOS("Relay can't be installed on {$this->os->getDisplayName()}");
        }
        $version = $versionRequirements === '' ? ltrim(trim($this->downloader->getContent('https://builds.r2.relay.so/meta/latest')), 'v') : $versionRequirements;
        if ($this->os instanceof OS\MacOS) {
            if (!self::isVersionAtLeast($version, '0.60.0')) {
                throw new Exception\UnsupportedOS("Relay {$version} can't be installed on {$this->os->getDisplayName()}: it requires OpenSSL 3, but the hiredis library of Homebrew uses OpenSSL 4");
            }
            $architectures = [
                Architecture::ARM64 => 'arm64',
            ];
        } else {
            $architectures = [
                Architecture::X64 => 'x86-64',
                Architecture::ARM64 => 'aarch64',
            ];
        }
        $architecture = $this->php->getArchitecture()->map($architectures, true);
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
        if ($this->os instanceof OS\MacOS) {
            $name = 'relay.so';
        } else {
            $name = $this->php->isThreadSafe() ? 'relay-zts.so' : 'relay-pkg.so';
        }
        $module = $rootDir . $fs->getDirectorySeparator() . $name;

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
        $module = $fs->readFile($modulePath);
        // Every installation must have its own ID: it's stored in the module as a placeholder
        $fs->writeFile($modulePath, str_replace('00000000-0000-0000-0000-000000000000', $this->generateUUID(), $module));
        if ($this->os instanceof OS\MacOS) {
            $this->relinkLibraries($this->os, $modulePath, $module);
        }
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

    /**
     * @throws \IPE\Exception\IO
     *
     * @return string
     */
    private function generateUUID()
    {
        $fs = $this->os->getFilesystem();
        if (!$this->os instanceof OS\MacOS) {
            return trim($fs->readFile('/proc/sys/kernel/random/uuid'));
        }
        list($rc, $output) = $fs->run(Command::create('uuidgen')->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        if ($rc !== 0) {
            throw new Exception\IO("uuidgen failed with exit code {$rc}");
        }

        return strtolower(trim($output));
    }

    /**
     * The builds for macOS link to versioned libraries in the default prefix of Homebrew, which only keeps their latest versions.
     *
     * @param \IPE\OS\MacOS $os
     * @param non-empty-string $modulePath
     * @param string $module the original contents of the module
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function relinkLibraries(OS\MacOS $os, $modulePath, $module)
    {
        $prefix = $os->getSystemPackager()->getPrefix();
        $matches = null;
        preg_match_all('#/opt/homebrew((?:/[\w.@+-]+)*/[\w@+-]+)(?:\.\d+)+\.dylib(?=\x00)#', $module, $matches, PREG_SET_ORDER);
        $arguments = [];
        foreach ($matches as $match) {
            if (!in_array($match[0], $arguments, true)) {
                array_push($arguments, '-change', $match[0], "{$prefix}{$match[1]}.dylib");
            }
        }
        if ($arguments === []) {
            return;
        }
        $arguments[] = $modulePath;
        // install_name_tool also updates the linker signature of the module, which then covers the UUID too
        list($rc) = $os->getFilesystem()->run(Command::create('install_name_tool', $arguments)->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\IO("install_name_tool failed with exit code {$rc}");
        }
    }
}
