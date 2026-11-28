<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libvpx extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-vpx';

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
                ['libvpx'],
                ['libvpx-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libvpx[0-9]+$'],
                ['libvpx-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libvpx'],
                ['libvpx-devel']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['libvpx']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
