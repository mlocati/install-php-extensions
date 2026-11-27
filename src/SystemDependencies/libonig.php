<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libonig extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-onig';

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
                ['oniguruma'],
                ['oniguruma-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libonig[0-9]+$'],
                ['libonig-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['oniguruma'],
                ['oniguruma-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['oniguruma']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
