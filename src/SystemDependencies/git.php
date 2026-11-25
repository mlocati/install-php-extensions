<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class git extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'git';

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
                [],
                ['git']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                [],
                ['git']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                [],
                ['git-core']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            // The Command Line Tools of Xcode, required to compile, include git
            return new Packages();
        }

        throw $this->createUnknownPackagesException();
    }
}
