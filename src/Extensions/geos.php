<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\OS;
use IPE\SystemDependencies;

/**
 * @see https://github.com/libgeos/php-geos
 */
final class geos extends Extension\GitHubExtension implements Extension\DeclaresUnsupportedEnvironments
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'geos';

    /**
     * @public
     * @var string
     */
    const COMMIT = '1a1c6a95697359892886a2050570007754ce575e';

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
     * @see \IPE\Extension\DeclaresUnsupportedEnvironments::getUnsupportedEnvironments()
     */
    public static function getUnsupportedEnvironments()
    {
        return [
            'alpine3.0..3.8' => "this system doesn't provide the GEOS library",
            'alpine3.9..3.10' => "the extension can't be compiled with the compiler or the libraries of this system",
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['geos'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getGitHubRepository()
     */
    protected function getGitHubRepository()
    {
        return 'libgeos/php-geos';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getLatestGitHubVersion()
     */
    protected function getLatestGitHubVersion()
    {
        if ($this->php->getMajorMinorVersion() < 700) {
            return 'dfe1ab17b0f155cc315bc13c75689371676e02e1';
        }
        if ($this->os instanceof OS\Debian && $this->os->getNumericVersion() <= OS\Debian::VERSION_STRETCH) {
            // Newer versions use GEOSMinimumRotatedRectangle_r, which requires GEOS 3.6+
            return '889e2b1d9aa6f82995db4dab6891f48c3bacfb59';
        }
        if ($this->os instanceof OS\Ubuntu && $this->os->getNumericVersion() <= OS\Ubuntu::XENIAL) {
            return '889e2b1d9aa6f82995db4dab6891f48c3bacfb59';
        }

        return self::COMMIT;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getSourceDependencies()
     */
    protected function getSourceDependencies($version)
    {
        return [SystemDependencies\libgeos::HANDLE];
    }
}
