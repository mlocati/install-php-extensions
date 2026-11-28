<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class liblmdb extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-lmdb';

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
        if ($this->os instanceof OS\Posix\AlpineLike) {
            return new Packages(
                ['lmdb'],
                ['lmdb-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['liblmdb0'],
                ['liblmdb-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['lmdb-libs'],
                ['lmdb-devel']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['lmdb']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
