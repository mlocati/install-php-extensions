<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class bsdcompatheaders extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'bsd-compat-headers';

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
                ['bsd-compat-headers']
            );
        }
        if ($this->os instanceof OS\Posix\WolfiLike) {
            // glibc includes the BSD headers
            return new Packages();
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                [],
                []
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages();
        }
        if ($this->os instanceof OS\MacOS) {
            // The SDK of macOS includes the BSD headers
            return new Packages();
        }

        throw $this->createUnknownPackagesException();
    }
}
