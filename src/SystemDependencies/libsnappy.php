<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libsnappy extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-snappy';

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
                ['snappy'],
                ['snappy-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['^libsnappy[0-9]+(v[0-9]+)?(t64)?$'],
                ['libsnappy-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
