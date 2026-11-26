<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\OS;
use IPE\SystemDependencies;
use IPE\SystemPackager;

/**
 * There are no releases that support PHP 8: we use the HEAD of the php8 branch.
 *
 * @see https://github.com/phpv8/v8js
 */
final class v8js extends Extension\GitHubExtension implements
    Extension\DeclaresUnsupportedEnvironments,
    Extension\ProvidesConfigureArguments,
    Extension\ProvidesSystemPackageNames
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'v8js';

    /**
     * @public
     * @var string
     */
    const COMMIT = '8a39efa3cf3b275e402ddf3c4f6b611a5f69a499';

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
        return ['8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresUnsupportedEnvironments::getUnsupportedEnvironments()
     */
    public static function getUnsupportedEnvironments()
    {
        $reason = 'the version of the V8 library of this system is too old';

        return [
            'debian10..11' => $reason,
            'ubuntu20.04..22.04' => $reason,
            'alpine3.12..3.18' => $reason,
            // The code uses APIs that newer versions of V8 removed (like PropertyCallbackInfo::This())
            'macos' => 'the version of the V8 library of this system is too recent',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['v8js'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getGitHubRepository()
     */
    protected function getGitHubRepository()
    {
        return 'phpv8/v8js';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getLatestGitHubVersion()
     */
    protected function getLatestGitHubVersion()
    {
        return self::COMMIT;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getSourceDependencies()
     */
    protected function getSourceDependencies($version)
    {
        return [SystemDependencies\libnode::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * On macOS, configure looks for V8 only in /usr/local and /usr.
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        $result = [];
        if ($this->os instanceof OS\MacOS) {
            $result[] = '--with-v8js=' . $this->os->getSystemPackager()->getFormulaPrefix('v8');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesSystemPackageNames::getSystemPackageNames()
     */
    public function getSystemPackageNames(SystemPackager\Package $phpPackage)
    {
        if ($this->os instanceof OS\MacOS) {
            // The modules of the extensions tap crash: they are built for a V8 library with the sandbox enabled
            return [];
        }

        return null;
    }
}
