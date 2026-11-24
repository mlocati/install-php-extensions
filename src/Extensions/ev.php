<?php

namespace IPE\Extensions;

use IPE\Extension;

final class ev extends Extension implements
    Extension\AvailableAsComposerPackage,
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\DeclaresPriority,
    Extension\DependsOnExtensions
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'ev';

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
        return ['ev'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsComposerPackage::getComposerPackageName()
     */
    public static function getComposerPackageName()
    {
        return 'osmanov/pecl-ev';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'ev';
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
        return $this->php->getMajorMinorVersion() <= 704 ? '1.1.5' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresPriority::getPriority()
     */
    public static function getPriority()
    {
        // Load it after sockets
        return 90;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getRequiredExtensions()
     */
    public function getRequiredExtensions($version)
    {
        // When PHP is configured with sockets (like the packages of Alpine), php_config.h defines HAVE_SOCKETS, and ev uses the sockets extension
        return preg_match('/[\s\'"]--enable-sockets(?:=(?!no[\s\'"])[^\s\'"]*)?[\s\'"]/', " {$this->php->getConfigureCommand()} ") ? [sockets::HANDLE] : [];
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
}
