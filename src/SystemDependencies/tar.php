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
        // BusyBox provides it on Alpine, and it's an essential package on Debian and Ubuntu
        if ($this->os instanceof OS\Alpine || $this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages();
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                [],
                ['tar']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
