<?php

namespace IPE\Extensions;

use IPE\Extension;

final class pdo_oci extends Extension\OracleDatabase implements
    Extension\AvailableAsComposerPackage,
    Extension\DependsOnExtensions,
    Extension\ProvidesConfigureEnvironment
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'pdo_oci';

    /**
     * @public
     * @var string
     */
    const CONFIGURE_OPTION = 'with-pdo-oci';

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
     * @see \IPE\Extension\DeclaresOptions::getOptions()
     */
    public static function getOptions()
    {
        return [self::createClientOption(self::CONFIGURE_OPTION)];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        $arguments = parent::getConfigureArguments($context);
        if ($this->php->getMajorMinorVersion() < 700) {
            // In PHP 5 the configure script can't detect the version of the Instant Client, and it doesn't accept the versions above 12.1.
            $arguments[0] .= ',12.1';
        }

        return $arguments;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\OracleDatabase::getConfigureOptionName()
     */
    protected function getConfigureOptionName()
    {
        return self::CONFIGURE_OPTION;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsComposerPackage::getComposerPackageName()
     */
    public static function getComposerPackageName()
    {
        return 'pecl/pdo_oci';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getRequiredExtensions()
     */
    public function getRequiredExtensions($version)
    {
        return [pdo::HANDLE];
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
     * @see \IPE\Extension\ProvidesConfigureEnvironment::getConfigureEnvironment()
     */
    public function getConfigureEnvironment(Extension\BuildContext $context)
    {
        // GCC 14 turned this warning into an error, and PHP 7 declares the read handler of the LOB stream with the wrong return type
        return $this->php->getMajorMinorVersion() < 800 ? ['CFLAGS' => '-Wno-incompatible-pointer-types'] : [];
    }
}
