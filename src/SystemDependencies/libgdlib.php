<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libgdlib extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-gdlib';

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
                ['libgd'],
                ['gd-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['libgd3'],
                ['libgd-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['gd'],
                ['gd-devel']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['gd']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
