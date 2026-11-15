<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libqdbm extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-qdbm';

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
            throw $this->createNotProvidedException();
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                ['^libqdbm14(t64)?$'],
                ['libqdbm-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['qdbm'],
                ['qdbm-devel']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
