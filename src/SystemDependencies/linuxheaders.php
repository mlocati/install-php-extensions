<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class linuxheaders extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'linux-headers';

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
                ['linux-headers']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                [],
                ['linux-libc-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                [],
                ['kernel-headers']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            throw $this->createNotProvidedException();
        }

        throw $this->createUnknownPackagesException();
    }
}
