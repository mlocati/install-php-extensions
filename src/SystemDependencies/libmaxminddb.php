<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libmaxminddb extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-maxminddb';

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
                ['libmaxminddb'],
                ['libmaxminddb-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::JESSIE) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['^libmaxminddb[0-9]*$'],
                ['libmaxminddb-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::TRUSTY) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['^libmaxminddb[0-9]*$'],
                ['libmaxminddb-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
