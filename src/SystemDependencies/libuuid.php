<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libuuid extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-uuid';

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
                ['libuuid'],
                ['util-linux-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                [],
                ['uuid-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libuuid'],
                ['libuuid-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            // The uuid functions of macOS don't include uuid_type() and uuid_variant()
            return new Packages(
                ['util-linux']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
