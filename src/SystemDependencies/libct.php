<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libct extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-ct';

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
        if ($this->os instanceof OS\Posix\AlpineLike) {
            return new Packages(
                ['freetds'],
                ['freetds-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['libct4'],
                ['freetds-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['freetds-libs'],
                ['freetds-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['freetds']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
