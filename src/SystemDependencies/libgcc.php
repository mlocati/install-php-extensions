<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libgcc extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-gcc';

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
                ['libgcc']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::BUSTER) {
                return new Packages(
                    ['libgcc1']
                );
            }

            return new Packages(
                ['libgcc-s1']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::BIONIC) {
                return new Packages(
                    ['libgcc1']
                );
            }

            return new Packages(
                ['libgcc-s1']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libgcc']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
