<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libsodium extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-sodium';

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
                ['libsodium'],
                ['libsodium-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libsodium[0-9]*$'],
                ['libsodium-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libsodium'],
                ['libsodium-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['libsodium']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
