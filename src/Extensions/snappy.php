<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

/**
 * @see https://github.com/kjdev/php-ext-snappy
 */
final class snappy extends Extension\GitHubExtension
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'snappy';

    /**
     * @public
     * @var string
     */
    const VERSION = '0.2.3';

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
        return ['snappy'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::getVendorConfigureArguments()
     */
    public function getVendorConfigureArguments($rootDir, $version, Option\Values $options)
    {
        return ['--with-snappy-includedir=/usr'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getGitHubRepository()
     */
    protected function getGitHubRepository()
    {
        return 'kjdev/php-ext-snappy';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getLatestGitHubVersion()
     */
    protected function getLatestGitHubVersion()
    {
        // These systems provide snappy 1.1.7, whose snappy-stubs-public.h has empty version numbers: the configure script of newer versions fails with it
        if ($this->os instanceof OS\Alpine && $this->os->getNumericVersion() <= 310) {
            return '0.2.1';
        }
        if ($this->os instanceof OS\Debian && $this->os->getNumericVersion() === OS\Debian::VERSION_BUSTER) {
            return '0.2.1';
        }
        if ($this->os instanceof OS\Ubuntu && $this->os->getNumericVersion() === OS\Ubuntu::BIONIC) {
            return '0.2.1';
        }

        return self::VERSION;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getSourceDependencies()
     */
    protected function getSourceDependencies($version)
    {
        return [SystemDependencies\libsnappy::HANDLE];
    }
}
