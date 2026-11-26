<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class tar extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'tar';

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
        if ($this->os instanceof OS\Alpine) {
            // BusyBox provides it
            return new Packages();
        }
        if ($this->os instanceof OS\Posix\DebianLike || $this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                [],
                ['tar']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            // macOS includes tar
            return new Packages();
        }

        throw $this->createUnknownPackagesException();
    }
}
