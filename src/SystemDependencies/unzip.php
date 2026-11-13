<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class unzip extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'unzip';

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
            if ($this->os->getNumericVersion() <= 307) {
                // BusyBox provides it, but it can't extract symbolic links
                return new Packages(
                    [],
                    ['libarchive-tools']
                );
            }

            return new Packages();
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                [],
                ['unzip']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                [],
                ['unzip']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
