<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libbrotli extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-brotli';

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
     * @return bool
     */
    public static function isProvidedByOS(OS $os)
    {
        if ($os instanceof OS\Posix\RHELLike) {
            return true;
        }
        if ($os instanceof OS\Alpine) {
            return $os->getNumericVersion() >= 312;
        }
        if ($os instanceof OS\Debian) {
            return $os->getNumericVersion() >= OS\Debian::BUSTER;
        }
        if ($os instanceof OS\Ubuntu) {
            return $os->getNumericVersion() > OS\Ubuntu::TRUSTY;
        }
        if ($os instanceof OS\MacOS) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        if (!self::isProvidedByOS($this->os)) {
            throw $this->createNotProvidedException();
        }
        if ($this->os instanceof OS\Alpine) {
            return new Packages(
                ['brotli-libs'],
                ['brotli-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['libbrotli1'],
                ['libbrotli-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike\EnterpriseLinux && $this->os->getNumericVersion() <= 8) {
            return new Packages(
                ['brotli'],
                ['brotli-devel']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libbrotli'],
                ['brotli-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['brotli']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
