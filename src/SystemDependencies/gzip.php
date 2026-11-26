<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class gzip extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'gzip';

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
            // BusyBox provides it
            return new Packages();
        }
        if ($this->os instanceof OS\Posix\DebianLike || $this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                [],
                ['gzip']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            // macOS includes gzip
            return new Packages();
        }

        throw $this->createUnknownPackagesException();
    }
}
