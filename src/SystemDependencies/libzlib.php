<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libzlib extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-zlib';

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
                [],
                ['zlib-dev']
            );
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                [],
                ['zlib1g-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            // The base system includes zlib (or zlib-ng-compat)
            return new Packages(
                [],
                ['zlib-devel']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
