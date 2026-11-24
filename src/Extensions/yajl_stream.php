<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\SystemDependencies;

/**
 * @see https://github.com/vlydev/php-yajl-stream
 */
final class yajl_stream extends Extension\GitHubExtension implements
    Extension\DeclaresUnsupportedEnvironments,
    Extension\IdentifiedByComposerPackage,
    Extension\ProvidesConfigureArguments
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'yajl_stream';

    /**
     * @public
     * @var string
     */
    const VERSION = '0.1.0';

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
        return ['8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresUnsupportedEnvironments::getUnsupportedEnvironments()
     */
    public static function getUnsupportedEnvironments()
    {
        return [
            'el10' => "this system doesn't provide the YAJL library",
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\IdentifiedByComposerPackage::getIdentifyingComposerPackageName()
     */
    public static function getIdentifyingComposerPackageName()
    {
        return 'vlydev/yajl-stream';
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
        return ['yajl_stream'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        return ['--with-yajl-stream'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getGitHubRepository()
     */
    protected function getGitHubRepository()
    {
        return 'vlydev/php-yajl-stream';
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
        return [SystemDependencies\libyajl::HANDLE];
    }
}
