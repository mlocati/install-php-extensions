<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class automake extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'automake';

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
                ['automake']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                [],
                ['automake']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
