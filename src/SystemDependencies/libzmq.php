<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libzmq extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-zmq';

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
                ['zeromq-dev'],
                []
            );
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                ['libzmq3-dev'],
                []
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['zeromq'],
                ['zeromq-devel']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
