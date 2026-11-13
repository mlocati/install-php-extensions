<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libodbc extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-odbc';

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
                ['unixodbc'],
                ['unixodbc-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::BOOKWORM) {
                return new Packages(
                    ['libodbc1'],
                    ['unixodbc-dev']
                );
            }

            return new Packages(
                ['libodbc2'],
                ['unixodbc-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::FOCAL) {
                return new Packages(
                    ['libodbc1'],
                    ['unixodbc-dev']
                );
            }

            return new Packages(
                ['libodbc2'],
                ['unixodbc-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['unixODBC'],
                ['unixODBC-devel']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
