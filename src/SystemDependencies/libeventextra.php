<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libeventextra extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-event-extra';

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
                ['libevent'],
                ['libevent-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['^libevent-openssl[0-9.-]*$', '^libevent-extra[0-9.-]*$', '^libevent-pthreads[0-9.-]*$'],
                ['libevent-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
