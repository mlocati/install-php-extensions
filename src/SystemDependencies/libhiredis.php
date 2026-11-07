<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libhiredis extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-hiredis';

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
            return new Packages(['hiredis', 'hiredis-ssl']);
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(['libhiredis1.1.0']);
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::JAMMY) {
                throw $this->createNotProvidedException();
            }

            return new Packages(['libhiredis1.1.0']);
        }

        throw $this->createUnknownPackagesException();
    }
}
