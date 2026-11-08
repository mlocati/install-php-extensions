<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\SystemDependencies;

final class dba extends Extension implements
    Extension\BundledInPHPSource,
    Extension\RequiresSystemDependencies,
    Extension\ProvidesConfigureArguments,
    Extension\PatchesSourceCode
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'dba';

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
            'dba',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return 'dba';
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
        return [SystemDependencies\libdb::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        return ['--with-db4'];
    }

    /**
     * The configure script of PHP 5.5 doesn't look for Berkeley DB 5.3, which is the only version provided by the operating systems.
     *
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PatchesSourceCode::patchSourceCode()
     */
    public function patchSourceCode(Extension\BuildContext $context)
    {
        $sourceDirectory = $context->getSourceDirectory();
        $fs = $this->os->getFilesystem();
        if ($this->php->getMajorMinorVersion() <= 505) {
            $fs->writeFile("{$sourceDirectory}/config.m4", str_replace('PHP_DBA_DB_CHECK(4, db-5.1 ', 'PHP_DBA_DB_CHECK(4, db-5.3 db-5.1 ', $fs->readFile("{$sourceDirectory}/config.m4")));
        }
    }
}
