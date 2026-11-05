<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libcares extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-cares';

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
                ['c-ares'],
                ['c-ares-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['libc-ares2'],
                ['libc-ares-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
