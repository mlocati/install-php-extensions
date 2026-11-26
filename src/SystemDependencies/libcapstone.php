<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libcapstone extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-capstone';

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
            if ($this->os->getNumericVersion() < 309) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['capstone'],
                ['capstone-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['^libcapstone[0-9]+$'],
                ['libcapstone-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::TRUSTY) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['^libcapstone[0-9]+$'],
                ['libcapstone-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['capstone'],
                ['capstone-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['capstone']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
