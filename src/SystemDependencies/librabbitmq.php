<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class librabbitmq extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-rabbitmq';

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
                ['rabbitmq-c'],
                ['rabbitmq-c-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['^librabbitmq[0-9]*(t64)?$'],
                ['librabbitmq-dev', 'libssh-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
