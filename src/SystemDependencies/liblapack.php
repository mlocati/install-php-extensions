<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class liblapack extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-lapack';

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
            $osVersion = $this->os->getNumericVersion();
            if ($osVersion <= 317) {
                $persistent = ['openblas', 'lapack'];
            } else {
                $persistent = ['openblas', 'liblapack'];
            }
            $volatile = ['lapack-dev', 'openblas-dev'];
            if ($osVersion <= 316) {
                $persistent[] = 'libexecinfo';
                $volatile[] = 'libexecinfo-dev';
            }
            if ($osVersion <= 310) {
                // openblas-dev ships some headers of lapack-dev
                $this->os->getSystemPackager()->setForceOverwrite(true);
            }

            return new Packages(
                $persistent,
                $volatile
            );
        }
        if ($this->os instanceof OS\Debian) {
            $osVersion = $this->os->getNumericVersion();
            $volatile = ['liblapack-dev', 'libopenblas-dev', 'liblapacke-dev'];
            if ($osVersion <= OS\Debian::VERSION_STRETCH) {
                return new Packages(
                    ['liblapacke', 'gfortran-6', 'libopenblas-base'],
                    array_merge($volatile, ['libgfortran-6-dev'])
                );
            }
            if ($osVersion <= OS\Debian::VERSION_BUSTER) {
                return new Packages(
                    ['liblapacke', 'gfortran-8', 'libopenblas-base'],
                    array_merge($volatile, ['libgfortran-8-dev'])
                );
            }
            if ($osVersion <= OS\Debian::VERSION_BULLSEYE) {
                return new Packages(
                    ['liblapacke', 'gfortran-10', 'libopenblas-base'],
                    array_merge($volatile, ['libgfortran-10-dev'])
                );
            }
            if ($osVersion <= OS\Debian::VERSION_BOOKWORM) {
                return new Packages(
                    ['liblapacke', 'gfortran-12', 'libopenblas0'],
                    array_merge($volatile, ['libgfortran-12-dev'])
                );
            }

            return new Packages(
                ['liblapacke', 'libgfortran5', 'libopenblas0'],
                array_merge($volatile, ['libgfortran-14-dev'])
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
