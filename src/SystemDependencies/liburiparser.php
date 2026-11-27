<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class liburiparser extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-uriparser';

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
                ['uriparser'],
                ['uriparser-dev']
            );
        }
        if ($this->os instanceof OS\Wolfi) {
            throw $this->createNotProvidedException();
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['liburiparser1'],
                ['liburiparser-dev']
            );
        }
        if ($this->os instanceof OS\AmazonLinux) {
            throw $this->createNotProvidedException();
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['uriparser'],
                ['uriparser-devel']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['uriparser']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
