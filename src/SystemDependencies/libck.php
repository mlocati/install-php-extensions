<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libck extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-ck';

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
            return new Packages(['ck']);
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::VERSION_STRETCH) {
                return new Packages(['libck0']);
            }
            if ($this->os->getNumericVersion() <= OS\Debian::VERSION_BUSTER) {
                throw $this->createNotProvidedException();
            }

            return new Packages(['^libck0(t64)?$']);
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::TRUSTY) {
                throw $this->createNotProvidedException();
            }

            return new Packages(['^libck0(t64)?$']);
        }

        throw $this->createUnknownPackagesException();
    }
}
