<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\OS;
use IPE\SystemDependencies;

/**
 * @see https://github.com/kjdev/php-ext-lz4
 */
final class lz4 extends Extension\GitHubExtension implements
    Extension\DeclaresUnsupportedEnvironments,
    Extension\ProvidesConfigureArguments
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lz4';

    /**
     * @public
     * @var string
     */
    const VERSION = '0.8.0';

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
        return ['5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5', '8.6'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresUnsupportedEnvironments::getUnsupportedEnvironments()
     */
    public static function getUnsupportedEnvironments()
    {
        return [
            'alpine3.0..3.8' => 'the version of the LZ4 library of this system is too old',
            'debian8' => 'the version of the LZ4 library of this system is too old',
            'ubuntu14.04' => 'the version of the LZ4 library of this system is too old',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['lz4'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        return ['--with-lz4-includedir=/usr'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getGitHubRepository()
     */
    protected function getGitHubRepository()
    {
        return 'kjdev/php-ext-lz4';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getLatestGitHubVersion()
     */
    protected function getLatestGitHubVersion()
    {
        // These systems provide liblz4 r131: newer versions require LZ4_versionString() and a more recent frame API
        if ($this->os instanceof OS\Debian && $this->os->getNumericVersion() === OS\Debian::VERSION_STRETCH) {
            return '0.4.4';
        }
        if ($this->os instanceof OS\Ubuntu && $this->os->getNumericVersion() >= OS\Ubuntu::XENIAL && $this->os->getNumericVersion() <= OS\Ubuntu::BIONIC) {
            return '0.4.4';
        }
        // Since 0.5.0 lz4 doesn't ship the arginfo header, and the stub declares constants, which the gen_stub.php of PHP 8.0 doesn't support
        if ($this->php->getMajorMinorVersion() === 800) {
            return '0.4.4';
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
        return [SystemDependencies\liblz4::HANDLE];
    }
}
