<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\SystemDependencies;

/**
 * @see https://github.com/vlydev/php-yajl-stream
 */
final class yajl_stream extends Extension\GitHubExtension
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
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['yajl_stream'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::getVendorConfigureArguments()
     */
    public function getVendorConfigureArguments($rootDir, $version, Option\Values $options)
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
