<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

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
     * @see \IPE\Extension\OracleDatabase::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        $result = parent::getRequiredSystemDependencies($version, $options);
        if ($this->usesDTrace()) {
            $result[] = SystemDependencies\dtrace::HANDLE;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\OracleDatabase::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        $result = parent::getConfigureArguments($context);
        if ($this->usesDTrace()) {
            $result[] = 'PHP_DTRACE=yes';
        }

        return $result;
    }

    /**
     * The php_config.h of a PHP built with oci8 and DTrace (like the one of Remi) defines HAVE_OCI8_DTRACE, and PHP doesn't tell how it was configured.
     *
     * @return bool
     */
    private function usesDTrace()
    {
        return $this->os instanceof OS\Posix\RHELLike;
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
