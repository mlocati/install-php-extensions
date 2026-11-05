<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

final class sockets extends Extension implements
    Extension\BundledInPHPSource,
    Extension\RequiresSystemDependencies,
    Extension\PatchesSourceCode
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'sockets';

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
            'sockets',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return 'sockets';
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
        return $this->os instanceof OS\Alpine && $this->php->getMajorMinorVersion() >= 802 ? [SystemDependencies\linuxheaders::HANDLE] : [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PatchesSourceCode::patchSourceCode()
     */
    public function patchSourceCode($sourceDirectory)
    {
        $fs = $this->os->getFilesystem();
        if (in_array($this->php->getBaseVersion(), ['8.0.15', '8.1.2'], true)) {
            // These PHP versions don't define _GNU_SOURCE when checking if the system has struct ucred.
            // Otherwise the check fails and the build stops because of the missing struct cmsgcred.
            $lines = explode("\n", $fs->readFile("{$sourceDirectory}/config.m4"));
            array_splice($lines, 69, 0, ['#ifndef _GNU_SOURCE', '#define _GNU_SOURCE', '#endif']);
            $fs->writeFile("{$sourceDirectory}/config.m4", implode("\n", $lines));
        }
    }
}
