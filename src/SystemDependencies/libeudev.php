<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

/**
 * The precompiled SourceGuardian loader for musl links to it.
 */
class libeudev extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-eudev';

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
                ['eudev-libs']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages();
        }

        throw $this->createUnknownPackagesException();
    }
}
