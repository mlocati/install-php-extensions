<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class librecode extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-recode';

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
                ['recode'],
                ['recode-dev']
            );
        }
        if ($this->os instanceof OS\Wolfi) {
            throw $this->createNotProvidedException();
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['librecode0'],
                ['librecode-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::NOBLE) {
                return new Packages(
                    ['librecode0'],
                    ['librecode-dev']
                );
            }

            return new Packages(
                ['librecode3'],
                ['librecode-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['recode'],
                ['recode-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['recode']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
