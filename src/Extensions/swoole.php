<?php

namespace IPE\Extensions;

use IPE\Exception;
use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

/**
 * The configure options are those of the config.m4 file of each version of swoole.
 */
final class swoole extends Extension implements
    Extension\AvailableAsComposerPackage,
    Extension\AvailableOnPECL,
    Extension\DeclaresConflicts,
    Extension\DeclaresDefaultVersion,
    Extension\DeclaresOptions,
    Extension\DependsOnExtensions,
    Extension\ProvidesConfigureArguments,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'swoole';

    /**
     * @public
     * @var string
     */
    const OPTION_WITHOUT_IOURING = 'without-iouring';

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
            'swoole',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsComposerPackage::getComposerPackageName()
     */
    public static function getComposerPackageName()
    {
        return 'swoole/swoole';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'swoole';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Event';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresConflicts::getConflictingExtensions()
     */
    public function getConflictingExtensions($version)
    {
        return [openswoole::HANDLE];
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
            // 2.0.11 causes a segmentation fault when libpcre-dev is installed (see https://github.com/mlocati/docker-php-extension-installer/issues/1282)
            return $this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu ? '2.0.10' : '2.0.11';
        }
        if ($phpVersion <= 700) {
            return '4.3.6';
        }
        if ($phpVersion <= 701) {
            return '4.5.10';
        }
        if ($phpVersion <= 704) {
            return '4.8.11';
        }
        if ($this->php->getBits() === 32) {
            // 32-bit systems are not supported since 5.1 (see https://github.com/swoole/swoole-src/issues/5198#issuecomment-1820162178), and 5.0 doesn't compile with PHP 8.3+
            if ($phpVersion >= 803) {
                throw new Exception\UnsupportedExtension('swoole 5.1+ does not support 32-bit systems, and older versions do not support PHP 8.3+');
            }

            return '5.0.3';
        }
        if ($this->os instanceof OS\Alpine) {
            if ($this->os->getNumericVersion() <= 312) {
                return '5.1.2';
            }
            if ($this->os->getNumericVersion() <= 315) {
                // Later versions fail with "'pthread_getname_np' was not declared in this scope"
                return '5.1.8';
            }
        }
        if ($phpVersion <= 800) {
            return '5.1.3';
        }

        return $phpVersion <= 801 ? '6.1.7' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresOptions::getOptions()
     */
    public static function getOptions()
    {
        return [
            new Option\CustomOption(self::OPTION_WITHOUT_IOURING, "Don't enable the io_uring support", false),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getRequiredExtensions()
     */
    public function getRequiredExtensions($version)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getOptionalExtensions()
     */
    public function getOptionalExtensions($version)
    {
        return [sockets::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        // Without zlib, swoole silently disables the HTTP compression (and 5.0.0 doesn't even compile without zlib and brotli)
        $result = [SystemDependencies\libopenssl::HANDLE, SystemDependencies\libstdcxx::HANDLE, SystemDependencies\libzlib::HANDLE, SystemDependencies\linuxheaders::HANDLE];
        if (self::usesPostgreSQL($version)) {
            $result[] = SystemDependencies\libpq::HANDLE;
        }
        if (self::isAtLeast($version, '4.6.0')) {
            $result[] = SystemDependencies\libcurl::HANDLE;
        }
        if (self::isAtLeast($version, '4.7.0')) {
            $result[] = SystemDependencies\libcares::HANDLE;
        }
        if (self::isAtLeast($version, '5.0.1')) {
            $result[] = SystemDependencies\libbrotli::HANDLE;
        }
        if ($this->usesSQLite($version)) {
            $result[] = SystemDependencies\libsqlite::HANDLE;
        }
        if ($this->usesZstd($version)) {
            $result[] = SystemDependencies\libzstd::HANDLE;
        }
        if ($this->usesIOUring($version, $options)) {
            $result[] = SystemDependencies\liburing::HANDLE;
        }
        if ($this->usesFirebird($version)) {
            $result[] = SystemDependencies\libfbclient::HANDLE;
        }
        if (self::isAtLeast($version, '6.2.0')) {
            $result[] = SystemDependencies\libssh2::HANDLE;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        $version = $context->getVersion();
        $result = [];
        if ($this->php->getBits() === 32 && $this->os->getMachineArchitecture() === OS\Architecture::X64) {
            // Otherwise configure detects the machine architecture and swoole fails to compile its x86_64 assembly code
            $result[] = $this->os instanceof OS\Alpine ? '--build=i686-linux-musl' : '--build=i686-linux-gnu';
        }
        if (!self::isAtLeast($version, '2.1.2')) {
            return $result;
        }
        if (in_array(sockets::HANDLE, $this->php->getInstalledExtensions(), true)) {
            // The configure script requires the headers of the sockets extension
            $result[] = '--enable-sockets';
        }
        $result[] = '--enable-mysqlnd';
        // Since 6.2.0 OpenSSL is always enabled: --with-openssl-dir only accepts a directory
        if (!self::isAtLeast($version, '6.2.0')) {
            $result[] = '--enable-openssl';
        }
        // Since 5.0.0 HTTP/2 is always enabled
        if (!self::isAtLeast($version, '5.0.0')) {
            $result[] = '--enable-http2';
        }
        if (self::usesPostgreSQL($version)) {
            $result[] = self::isAtLeast($version, '5.0.0') ? '--enable-swoole-pgsql' : '--enable-coroutine-postgresql';
        }
        if (self::isAtLeast($version, '4.5.6') && !self::isAtLeast($version, '5.0.0')) {
            $result[] = '--enable-swoole-json';
        }
        if (self::isAtLeast($version, '4.6.0')) {
            $result[] = '--enable-swoole-curl';
        }
        if (self::isAtLeast($version, '4.7.0')) {
            $result[] = '--enable-cares';
        }
        if (self::isAtLeast($version, '5.0.1')) {
            $result[] = '--enable-brotli';
        }
        if ($this->usesSQLite($version)) {
            $result[] = '--enable-swoole-sqlite';
        }
        if ($this->usesZstd($version)) {
            $result[] = '--enable-zstd';
        }
        if ($this->php->isThreadSafe() && self::isAtLeast($version, '6.0.0')) {
            $result[] = '--enable-swoole-thread';
        }
        if ($this->usesIOUring($version, $context->getOptionValues())) {
            $result[] = '--enable-iouring';
            if (self::isAtLeast($version, '6.2.0')) {
                $result[] = '--enable-uring-socket';
            }
        }
        if ($this->usesFirebird($version)) {
            $result[] = '--with-swoole-firebird';
        }
        if (self::isAtLeast($version, '6.2.0')) {
            $result[] = '--with-swoole-ssh2';
        }

        return $result;
    }

    /**
     * @param string $version
     * @param non-empty-string $minimum
     *
     * @return bool
     */
    private static function isAtLeast($version, $minimum)
    {
        return $version === '' || version_compare($version, $minimum) >= 0;
    }

    /**
     * Between 4.4.0 and 4.8.13 swoole doesn't have any option to enable the PostgreSQL client.
     *
     * @param string $version
     *
     * @return bool
     */
    private static function usesPostgreSQL($version)
    {
        return self::isAtLeast($version, '5.0.0') || (self::isAtLeast($version, '2.1.2') && !self::isAtLeast($version, '4.4.0'));
    }

    /**
     * @param string $version
     *
     * @return bool
     */
    private function usesSQLite($version)
    {
        return self::isAtLeast($version, '5.1.0');
    }

    /**
     * @param string $version
     *
     * @return bool
     */
    private function usesZstd($version)
    {
        if (!self::isAtLeast($version, '6.0.0')) {
            return false;
        }

        // swoole requires libzstd 1.4.0+, but Debian Buster provides 1.3.8 and Ubuntu 18.04 provides 1.3.3
        if ($this->os instanceof OS\Debian) {
            return $this->os->getNumericVersion() >= OS\Debian::VERSION_BULLSEYE;
        }
        if ($this->os instanceof OS\Ubuntu) {
            return $this->os->getNumericVersion() >= OS\Ubuntu::FOCAL;
        }

        return true;
    }

    /**
     * @param string $version
     *
     * @return bool
     */
    private function usesIOUring($version, Option\Values $options)
    {
        if (!self::isAtLeast($version, '6.0.0') || $options->hasCustomValue(self::OPTION_WITHOUT_IOURING)) {
            return false;
        }
        if ($this->os instanceof OS\Alpine) {
            // Alpine 3.19 fails with "invalid use of incomplete type 'const struct statx'"; swoole 6.2.0+ requires liburing 2.8+, available since Alpine 3.21
            return $this->os->getNumericVersion() >= (self::isAtLeast($version, '6.2.0') ? 321 : 320);
        }
        // swoole requires liburing 2.0+ (2.5+ for 6.0.0, 2.8+ for 6.2.0): we only use it where it's known to work
        if ($this->os instanceof OS\Debian) {
            return $this->os->getNumericVersion() >= OS\Debian::VERSION_TRIXIE;
        }
        if ($this->os instanceof OS\Ubuntu) {
            return $this->os->getNumericVersion() >= OS\Ubuntu::RESOLUTE;
        }

        return false;
    }

    /**
     * @param string $version
     *
     * @return bool
     */
    private function usesFirebird($version)
    {
        if (!self::isAtLeast($version, '6.2.0')) {
            return false;
        }

        // swoole requires Firebird 3+, but on Alpine with PHP < 8.4 we use Firebird 2.5 (see libfbclient)
        return !$this->os instanceof OS\Alpine || $this->php->getMajorMinorVersion() >= 804;
    }
}
