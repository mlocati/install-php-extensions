<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libyaml extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-yaml';

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
                ['yaml'],
                ['yaml-dev']
            );
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                ['libyaml-0-2'],
                ['libyaml-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libyaml'],
                ['libyaml-devel']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
