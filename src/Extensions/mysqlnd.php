<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\SystemDependencies;

final class mysqlnd extends Extension implements
    Extension\BundledInPHPSource,
    Extension\DeclaresPriority,
    Extension\PatchesSourceCode,
    Extension\ProvidesConfigureArguments,
    Extension\ProvidesPreprocessorFlags,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'mysqlnd';

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
        return [
            'mysqlnd',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return 'mysqlnd';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getBundledPHPVersions()
     */
    public static function getBundledPHPVersions()
    {
        return '*';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresPriority::getPriority()
     */
    public static function getPriority()
    {
        // It must be loaded before the extensions that use it (mysqli and pdo_mysql)
        return 10;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        // OpenSSL for the extended SSL support, zlib for the compressed protocol
        return [SystemDependencies\libopenssl::HANDLE, SystemDependencies\libzlib::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        if ($this->php->getMajorMinorVersion() < 804) {
            // phpize builds enable the extended SSL support implicitly (php_config.h defines MYSQLND_HAVE_SSL), but OpenSSL is searched only if PHP_OPENSSL_DIR is yes or a path
            return ['PHP_OPENSSL_DIR=yes'];
        }

        return ['--with-mysqlnd-ssl'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesPreprocessorFlags::getPreprocessorFlags()
     */
    public function getPreprocessorFlags(Extension\BuildContext $context)
    {
        // php_mysqlnd.c includes config.h only since recent patch releases (not in 8.1.2 nor in 8.2.0, for example): without this it doesn't export get_module
        return '-DCOMPILE_DL_MYSQLND=1';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PatchesSourceCode::patchSourceCode()
     */
    public function patchSourceCode(Extension\BuildContext $context)
    {
        if ($this->php->getMajorMinorVersion() >= 804) {
            return;
        }
        // Otherwise phpize builds don't link the libraries (OpenSSL and zlib), and the module only loads if PHP has already loaded them
        $configM4 = $context->getSourceDirectory() . '/config.m4';
        $fs = $this->os->getFilesystem();
        $fs->writeFile($configM4, $fs->readFile($configM4) . "\nPHP_SUBST([MYSQLND_SHARED_LIBADD])\n");
    }
}
