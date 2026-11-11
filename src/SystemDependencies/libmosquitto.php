<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libmosquitto extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-mosquitto';

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
                ['mosquitto-libs'],
                ['mosquitto-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['^libmosquitto1(t64)?$'],
                ['libmosquitto-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::TRUSTY) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['^libmosquitto1(t64)?$'],
                ['libmosquitto-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
