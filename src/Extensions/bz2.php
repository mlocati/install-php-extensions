<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

final class bz2 extends Extension implements
    Extension\BundledInPHPSource,
    Extension\ProvidesConfigureArguments,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'bz2';

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
            'bz2',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return 'bz2';
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
        return [SystemDependencies\libbz2::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        $result = [];
        if ($this->os instanceof OS\MacOS) {
            $sdkPath = $this->os->getSDKPath();
            if ($sdkPath !== '') {
                // configure looks for the headers only in /usr/local and /usr, but macOS has them only in its SDK
                $result[] = "--with-bz2={$sdkPath}/usr";
            }
        }

        return $result;
    }
}
