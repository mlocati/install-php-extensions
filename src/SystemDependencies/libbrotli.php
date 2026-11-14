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
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        if ($this->os instanceof OS\Alpine) {
            return new Packages(
                ['brotli-libs'],
                ['brotli-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['libbrotli1'],
                ['libbrotli-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::TRUSTY) {
                throw $this->createNotProvidedException();
            }

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

        throw $this->createUnknownPackagesException();
    }
}
