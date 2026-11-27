<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class findutils extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'findutils';

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
            // BusyBox provides find
            return new Packages();
        }
        if ($this->os instanceof OS\Posix\DebianLike || $this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                [],
                ['findutils']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            // macOS includes find
            return new Packages();
        }

        throw $this->createUnknownPackagesException();
    }
}
