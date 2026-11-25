<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;
use IPE\SystemPackager;

final class http extends Extension implements
    Extension\AvailableAsComposerPackage,
    Extension\AvailableOnPECL,
    Extension\DeclaresConflicts,
    Extension\DeclaresDefaultVersion,
    Extension\DeclaresPriority,
    Extension\DependsOnExtensions,
    Extension\ProvidesConfigureArguments,
    Extension\ProvidesSystemPackageNames,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'http';

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
            'http',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsComposerPackage::getComposerPackageName()
     */
    public static function getComposerPackageName()
    {
        return 'm6w6/ext-http';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'pecl_http';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'HTTP';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresConflicts::getConflictingExtensions()
     */
    public function getConflictingExtensions($version, $options = null)
    {
        return [parallel::HANDLE => 'PHP crashes at shutdown in raphf if parallel started a thread'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        $phpVersion = $this->php->getMajorMinorVersion();
        if ($phpVersion <= 506) {
            return '2.6.0';
        }

        return $phpVersion <= 704 ? '3.2.4' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getRequiredExtensions()
     */
    public function getRequiredExtensions($version)
    {
        return $this->php->getMajorMinorVersion() <= 704 ? [propro::HANDLE, raphf::HANDLE] : [raphf::HANDLE];
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
        $os = $this->os;
        if (!$os instanceof OS\Alpine && !$os instanceof OS\Posix\DebianLike && !$os instanceof OS\Posix\RHELLike && !$os instanceof OS\MacOS) {
            throw $this->createUnknownOSException();
        }
        $result = [SystemDependencies\libcurl::HANDLE, SystemDependencies\libevent::HANDLE, SystemDependencies\libzlib::HANDLE];
        // The configure script always uses brotli when it finds it (for example, libcurl-devel depends on brotli-devel on EL 10)
        if (self::isVersionAtLeast($version, '3.2.0') && SystemDependencies\libbrotli::isProvidedByOS($os)) {
            $result[] = SystemDependencies\libbrotli::HANDLE;
        }
        if (self::isVersionAtLeast($version, '3.1.0')) {
            $result[] = SystemDependencies\libicu::HANDLE;
            // On macOS it's optional, and IPE would compile it in /usr/local
            if (!$os instanceof OS\MacOS) {
                $result[] = SystemDependencies\libidnkit::HANDLE;
            }
        }
        $v3 = self::isVersionAtLeast($version, '3.0.0');
        // The libcurl of Debian and Ubuntu uses GnuTLS (the other ones use OpenSSL)
        if ($v3 && $os instanceof OS\Posix\DebianLike) {
            $result[] = SystemDependencies\libgcrypt::HANDLE;
        }
        $result[] = !$v3 || $os instanceof OS\Alpine ? SystemDependencies\libidn::HANDLE : SystemDependencies\libidn2::HANDLE;
        // On Alpine, curl-dev provides the SSL headers
        if (!$os instanceof OS\Alpine) {
            $result[] = SystemDependencies\libopenssl::HANDLE;
        }
        sort($result);

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresPriority::getPriority()
     */
    public static function getPriority()
    {
        return 90;
    }

    /**
     * {@inheritdoc}
     *
     * On macOS, configure looks for zlib and libidn2 only in /usr/local, /usr and /opt, and it would use the curl-config of the system.
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        if (!$this->os instanceof OS\MacOS) {
            return [];
        }
        $homebrew = $this->os->getSystemPackager();
        $result = [
            '--with-http-libcurl-dir=' . $homebrew->getFormulaPrefix('curl'),
            '--with-http-libidn2-dir=' . $homebrew->getFormulaPrefix('libidn2'),
        ];
        $sdkPath = $this->os->getSDKPath();
        if ($sdkPath !== '') {
            $result[] = "--with-http-zlib-dir={$sdkPath}/usr";
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesSystemPackageNames::getSystemPackageNames()
     */
    public function getSystemPackageNames(SystemPackager\Package $phpPackage)
    {
        if ($this->os instanceof OS\MacOS) {
            return $this->os->getSystemPackager()->getExtensionFormulae($phpPackage, ['pecl_http']);
        }

        return null;
    }
}
