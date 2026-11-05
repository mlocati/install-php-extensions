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

        throw $this->createUnknownPackagesException();
    }
}
