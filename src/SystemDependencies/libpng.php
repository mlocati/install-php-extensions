<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libpng extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-png';

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
                ['libpng'],
                ['libpng-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libpng[0-9]+-[0-9]+$'],
                ['libpng-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libpng'],
                ['libpng-devel']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['libpng']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
