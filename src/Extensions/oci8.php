<?php

namespace IPE\Extensions;

use IPE\Extension;

final class oci8 extends Extension\OracleDatabase implements Extension\AvailableAsComposerPackage
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'oci8';

    /**
     * @public
     * @var string
     */
    const CONFIGURE_OPTION = 'with-oci8';

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
     * @see \IPE\Extension\DeclaresOptions::getOptions()
     */
    public static function getOptions()
    {
        return [self::createClientOption(self::CONFIGURE_OPTION)];
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
        return 'oci8/oci8';
    }
}
