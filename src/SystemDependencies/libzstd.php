<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libzstd extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-zstd';

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
                ['zstd-libs'],
                ['zstd-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['libzstd1'],
                ['libzstd-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::TRUSTY) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['libzstd1'],
                ['libzstd-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
