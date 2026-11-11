<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class liburing extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-uring';

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
                ['liburing'],
                ['liburing-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['liburing2'],
                ['liburing-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::FOCAL) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['liburing2'],
                ['liburing-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
