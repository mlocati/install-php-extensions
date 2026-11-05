<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libsqlite extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-sqlite';

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
                ['sqlite-libs'],
                ['sqlite-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['libsqlite3-0'],
                ['libsqlite3-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
