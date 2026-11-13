<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libuv extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-uv';

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
                ['libuv'],
                ['libuv-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::JESSIE) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['^libuv1(t64)?$'],
                ['libuv1-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::TRUSTY) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['^libuv1(t64)?$'],
                ['libuv1-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libuv'],
                ['libuv-devel']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
