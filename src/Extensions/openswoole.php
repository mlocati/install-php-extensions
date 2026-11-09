<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\SystemDependencies;

final class openswoole extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresConflicts,
    Extension\DeclaresDefaultVersion,
    Extension\DeclaresPriority,
    Extension\DependsOnExtensions,
    Extension\ProvidesConfigureArguments,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'openswoole';

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
        return ['7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['openswoole'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'openswoole';
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
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        $phpVersion = $this->php->getMajorMinorVersion();
        if ($phpVersion <= 703) {
            return '4.10.0';
        }
        if ($phpVersion <= 800) {
            return '22.0.0';
        }
        if ($phpVersion <= 801) {
            return '22.1.2';
        }

        return $phpVersion <= 802 ? '25.2.0' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getRequiredExtensions()
     */
    public function getRequiredExtensions($version)
    {
        // Because of --enable-mysqlnd and of the curl hooks
        return [mysqlnd::HANDLE, curl::HANDLE];
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
        return [SystemDependencies\libcurl::HANDLE, SystemDependencies\libopenssl::HANDLE, SystemDependencies\libpq::HANDLE, SystemDependencies\libstdcxx::HANDLE, SystemDependencies\linuxheaders::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        $version = $context->getVersion();
        $result = ['--enable-openssl', '--enable-http2', '--enable-mysqlnd'];
        if (in_array(sockets::HANDLE, $this->php->getInstalledExtensions(), true)) {
            // The configure script requires the headers of the sockets extension
            $result[] = '--enable-sockets';
        }
        if (self::isVersionAtLeast($version, '4.8.0')) {
            $result[] = '--with-postgres';
        }
        if (self::isVersionAtLeast($version, '22.0.0')) {
            $result[] = '--enable-hook-curl';
        } else {
            $result[] = '--enable-swoole-json';
            $result[] = '--enable-swoole-curl';
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresConflicts::getConflictingExtensions()
     */
    public function getConflictingExtensions($version)
    {
        return [swoole::HANDLE];
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
