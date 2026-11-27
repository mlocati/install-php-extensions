<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libmemcached extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-memcached';

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
                ['libmemcached-libs'],
                ['libmemcached-dev']
            );
        }
        if ($this->os instanceof OS\Wolfi) {
            return new Packages(
                ['libmemcached'],
                ['libmemcached-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libmemcachedutil2(t64)?$'],
                ['libmemcached-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike\EnterpriseLinux && $this->os->getNumericVersion() <= 8) {
            return new Packages(
                ['libmemcached-libs'],
                ['libmemcached-devel']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libmemcached-awesome'],
                ['libmemcached-awesome-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['libmemcached']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
