<?php

namespace IPE\Extension;

use IPE\Exception;
use IPE\Extension;
use IPE\Option;
use IPE\Option\ConfigureOption;
use IPE\OS;
use IPE\SystemDependencies\oracleinstantclient;

/**
 * What oci8 and pdo_oci have in common.
 * They must be loaded after smbclient, otherwise smbclient_open causes a segmentation fault.
 */
abstract class OracleDatabase extends Extension implements
    AvailableOnPECL,
    BundledInPHPSource,
    DeclaresOptions,
    DeclaresPriority,
    ProvidesConfigureArguments,
    RequiresSystemDependencies
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return [static::getHandle()];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return static::getHandle();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Database';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return static::getHandle();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getBundledPHPVersions()
     */
    public static function getBundledPHPVersions()
    {
        return '<8.4';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [oracleinstantclient::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments($version, Option\Values $options)
    {
        if (!$this->os instanceof OS\Posix) {
            throw new Exception\UnsupportedOS(static::getHandle() . " can't be installed on {$this->os->getDisplayName()}");
        }

        return ['--' . $this->getConfigureOptionName() . '=instantclient,' . oracleinstantclient::getLibraryPath($this->php, $this->os)];
    }

    /**
     * Declaring it without a default hides the one of the extension manifest (autodetect), which doesn't find the Instant Client.
     *
     * @param non-empty-string $name
     *
     * @return \IPE\Option\ConfigureOption
     */
    protected static function createClientOption($name)
    {
        return new ConfigureOption($name, 'The kind and the path of the Oracle client', true);
    }

    /**
     * @return non-empty-string
     *
     * @example 'with-oci8'
     */
    abstract protected function getConfigureOptionName();

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
