<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\SystemDependencies;

/**
 * The latest release doesn't support recent versions of PHP: we use the HEAD of the master branch.
 *
 * @see https://github.com/zeromq/php-zmq
 */
final class zmq extends Extension\GitHubExtension
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'zmq';

    /**
     * @public
     * @var string
     */
    const COMMIT = '616b6c64ffd3866ed038615494306dd464ab53fc';

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
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['zmq'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getGitHubRepository()
     */
    protected function getGitHubRepository()
    {
        return 'zeromq/php-zmq';
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
        return [SystemDependencies\libzmq::HANDLE];
    }
}
