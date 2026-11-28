<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class bash extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'bash';

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
        if ($this->os instanceof OS\Posix\AlpineLike) {
            return new Packages(
                [],
                ['bash']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike || $this->os instanceof OS\Posix\RHELLike || $this->os instanceof OS\MacOS) {
            // Always installed
            return new Packages();
        }

        throw $this->createUnknownPackagesException();
    }
}
