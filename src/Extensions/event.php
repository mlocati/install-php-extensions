<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\PHPInfo\SSLLibrary;
use IPE\SystemDependencies;

final class event extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresPriority,
    Extension\DependsOnExtensions,
    Extension\ProvidesConfigureArguments,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'event';

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
        return ['event'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'event';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Networking';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getRequiredExtensions()
     */
    public function getRequiredExtensions($version)
    {
        // With PHP 5, the build fails without the sockets support (EventUtil::createSocket is registered anyway)
        return $this->php->getMajorMinorVersion() < 700 ? [sockets::HANDLE] : [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getOptionalExtensions()
     */
    public function getOptionalExtensions($version)
    {
        return $this->php->getMajorMinorVersion() < 700 ? [] : [sockets::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\libopenssl::HANDLE, SystemDependencies\libevent::HANDLE, SystemDependencies\libeventextra::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        $version = $context->getVersion();
        if ($version !== '' && version_compare($version, '1.3.0') < 0) {
            return [];
        }
        $result = ['--with-event-libevent-dir=/usr', '--with-event-pthreads', '--with-event-extra'];
        $sslLibrary = $this->php->getSSLLibrary();
        // The PHP 5 of the Docker images of Debian Stretch uses OpenSSL 1.0, libevent uses OpenSSL 1.1: mixing them crashes the SSL classes of event
        if ($this->os instanceof OS\Debian && $this->os->getNumericVersion() === OS\Debian::VERSION_STRETCH && $sslLibrary !== null && $sslLibrary->getType() === SSLLibrary::TYPE_OPENSSL && $sslLibrary->getMajorMinorVersion() === '1.0') {
            $result[] = '--without-event-openssl';
        } else {
            $result[] = '--with-event-openssl';
        }
        if ($version === '' || version_compare($version, '1.7.6') >= 0) {
            // The sockets support requires the headers of the sockets extension
            $result[] = in_array(sockets::HANDLE, $this->php->getInstalledExtensions(), true) ? '--enable-event-sockets' : '--disable-event-sockets';
        }

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
}
