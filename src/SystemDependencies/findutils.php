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
        // BusyBox provides find on Alpine, and findutils is an essential package on Debian and Ubuntu
        if ($this->os instanceof OS\Alpine || $this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages();
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                [],
                ['findutils']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
