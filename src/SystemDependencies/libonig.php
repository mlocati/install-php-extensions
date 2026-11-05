<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libonig extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-onig';

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
                ['oniguruma'],
                ['oniguruma-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['^libonig[0-9]+$'],
                ['libonig-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
