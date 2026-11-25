<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libidn2 extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-idn2';

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
            return new Packages(
                ['libidn2'],
                ['libidn2-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libidn2-[0-9+]$'],
                ['^libidn2-[0-9+]-dev$']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libidn2'],
                ['libidn2-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['libidn2']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
