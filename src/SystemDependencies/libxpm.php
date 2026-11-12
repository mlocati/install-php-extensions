<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libxpm extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-xpm';

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
                ['libxpm'],
                ['libxpm-dev']
            );
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                ['libxpm4'],
                ['libxpm-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libXpm'],
                ['libXpm-devel']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
