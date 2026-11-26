<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libdb extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-db';

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
                ['db'],
                ['db-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                [],
                ['^libdb5\.3-dev$']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libdb'],
                ['libdb-devel']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['berkeley-db@5']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
