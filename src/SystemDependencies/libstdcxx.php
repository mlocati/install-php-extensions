<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libstdcxx extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-stdcxx';

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
                ['libstdc++']
            );
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages();
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libstdc++']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
