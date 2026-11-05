<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\Service\Regex;
use IPE\SystemDependencies;

final class odbc extends Extension implements
    Extension\BundledInPHPSource,
    Extension\RequiresSystemDependencies,
    Extension\ProvidesConfigureArguments,
    Extension\PatchesConfigureScript
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'odbc';

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
            'odbc',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return 'odbc';
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
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\libodbc::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments($version, Option\Values $options)
    {
        return ['--with-unixODBC=shared,/usr'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PatchesConfigureScript::patchConfigureScript()
     */
    public function patchConfigureScript($sourceDirectory)
    {
        $fs = $this->os->getFilesystem();
        $configure = "{$sourceDirectory}/configure";
        // Otherwise configure enables the other ODBC drivers too, and fails looking for their headers (eg /usr/local/incl/sqlext.h).
        $fs->writeFile(
            $configure,
            Regex::replace(
                '/^ *test +"\$PHP_.*" *= *"no" *&& *PHP_.*=yes *$/m',
                '#\0',
                $fs->readFile($configure)
            )
        );
    }
}
