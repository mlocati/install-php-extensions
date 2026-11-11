<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

/**
 * The curl command (or wget), for the build scripts that download files.
 */
final class curl extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'curl';

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
            // BusyBox provides wget
            return new Packages();
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                [],
                ['curl']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
