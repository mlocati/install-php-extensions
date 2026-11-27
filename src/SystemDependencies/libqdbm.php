<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libqdbm extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-qdbm';

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
            throw $this->createNotProvidedException();
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libqdbm14(t64)?$'],
                ['libqdbm-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['qdbm'],
                ['qdbm-devel']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['qdbm']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
