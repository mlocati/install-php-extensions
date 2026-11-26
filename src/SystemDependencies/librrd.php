<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class librrd extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-rrd';

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
                ['librrd'],
                ['rrdtool-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^librrd[0-9]*(t64)?$'],
                ['librrd-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['rrdtool'],
                ['rrdtool-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['rrdtool']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
