<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libpython extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-python';

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
                ['python3'],
                ['python3-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['^libpython3\.[0-9]+(t64)?$'],
                ['python3-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
