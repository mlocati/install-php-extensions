<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libidn extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-idn';

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
                ['libidn'],
                ['libidn-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libidn1[0-9]$'],
                ['^libidn1[0-9+]-dev$']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libidn'],
                ['libidn-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['libidn']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
