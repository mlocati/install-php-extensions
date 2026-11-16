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
        if ($this->os instanceof OS\Alpine) {
            // BusyBox provides find
            return new Packages();
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu || $this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                [],
                ['findutils']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
