<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class cmake extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'cmake';

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
        if ($this->os instanceof OS\Posix\AlpineLike || $this->os instanceof OS\Posix\DebianLike || $this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                [],
                ['cmake']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                [],
                ['cmake']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
