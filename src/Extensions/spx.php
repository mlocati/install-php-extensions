<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\OS;
use IPE\SystemDependencies;

/**
 * @see https://github.com/NoiseByNorthwest/php-spx
 */
final class spx extends Extension\GitHubExtension implements
    Extension\IdentifiedByComposerPackage,
    Extension\ProvidesConfigureArguments
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'spx';

    /**
     * @public
     * @var string
     */
    const VERSION = '0.4.22';

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
     * @see \IPE\Extension\IdentifiedByComposerPackage::getIdentifyingComposerPackageName()
     */
    public static function getIdentifyingComposerPackageName()
    {
        return 'noisebynorthwest/php-spx';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\IdentifiedByComposerPackage::getExtensionVersionFromComposerVersion()
     */
    public function getExtensionVersionFromComposerVersion($composerVersion)
    {
        return preg_match('/^v(\d+(\.\d+)*)$/D', $composerVersion, $matches) ? $matches[1] : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['SPX'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getGitHubRepository()
     */
    protected function getGitHubRepository()
    {
        return 'NoiseByNorthwest/php-spx';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getGitReference()
     */
    protected function getGitReference($version)
    {
        return "v{$version}";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getLatestGitHubVersion()
     */
    protected function getLatestGitHubVersion()
    {
        return self::VERSION;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getSourceDependencies()
     */
    protected function getSourceDependencies($version)
    {
        return [SystemDependencies\libzlib::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * On macOS, zlib is in the SDK, but configure looks for it only in /usr/local, /usr and /opt/local.
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        $result = [];
        if ($this->os instanceof OS\MacOS) {
            $sdkPath = $this->os->getSDKPath();
            if ($sdkPath !== '') {
                $result[] = "--with-zlib-dir={$sdkPath}/usr";
            }
        }

        return $result;
    }
}
