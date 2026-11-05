<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\SystemDependencies;

/**
 * @see https://github.com/wikimedia/mediawiki-php-wikidiff2
 */
final class wikidiff2 extends Extension\GitHubExtension implements Extension\DeclaresUnsupportedEnvironments
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'wikidiff2';

    /**
     * @public
     * @var string
     */
    const VERSION = '1.14.2';

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
        return ['7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresUnsupportedEnvironments::getUnsupportedEnvironments()
     */
    public static function getUnsupportedEnvironments()
    {
        return [
            'debian8..9' => "the extension can't be compiled with the compiler or the libraries of this system",
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['wikidiff2'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getGitHubRepository()
     */
    protected function getGitHubRepository()
    {
        return 'wikimedia/mediawiki-php-wikidiff2';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getLatestGitHubVersion()
     */
    protected function getLatestGitHubVersion()
    {
        $phpVersion = $this->php->getMajorMinorVersion();
        if ($phpVersion <= 702) {
            return '1.13.0';
        }

        return $phpVersion <= 801 ? '1.14.1' : self::VERSION;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getSourceDependencies()
     */
    protected function getSourceDependencies($version)
    {
        return [SystemDependencies\libstdcxx::HANDLE, SystemDependencies\libthai::HANDLE];
    }
}
