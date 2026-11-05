<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libvpx extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-vpx';

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
                ['libvpx'],
                ['libvpx-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['^libvpx[0-9]+$'],
                ['libvpx-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
