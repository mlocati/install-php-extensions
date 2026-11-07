<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libgraphicsmagick extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-graphicsmagick';

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
                ['graphicsmagick', 'libgomp'],
                ['graphicsmagick-dev', 'libtool']
            );
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                ['^libgraphicsmagick(-q16-)?[0-9]*$'],
                ['libgraphicsmagick1-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
