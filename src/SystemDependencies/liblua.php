<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class liblua extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-lua';

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
                ['lua5.1-libs'],
                ['lua5.1-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['liblua5.1-0'],
                ['liblua5.1-0-dev']
            );
        }
        if ($this->os instanceof OS\AmazonLinux) {
            throw $this->createNotProvidedException();
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['compat-lua-libs'],
                ['compat-lua-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            throw $this->createNotProvidedException();
        }

        throw $this->createUnknownPackagesException();
    }
}
