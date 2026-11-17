<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

/**
 * The curl command, for the scripts that download files.
 */
final class curl extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'curl';

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getHandle()
     */
    public static function getHandle()
    {
        return self::HANDLE;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        if ($this->os instanceof OS\Alpine || $this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                [],
                ['curl']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            // The curl package conflicts with curl-minimal, which provides the command too
            if ($this->os->getFilesystem()->resolveCommand('curl') !== '') {
                return new Packages();
            }

            return new Packages(
                [],
                ['curl']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
