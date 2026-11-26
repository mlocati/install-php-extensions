<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class nasm extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'nasm';

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
        if ($this->os instanceof OS\Alpine || $this->os instanceof OS\Posix\DebianLike || $this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                [],
                ['nasm']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                [],
                ['nasm']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
