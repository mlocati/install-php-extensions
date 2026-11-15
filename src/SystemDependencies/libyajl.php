<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libyajl extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-yajl';

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
                ['yajl'],
                ['yajl-dev']
            );
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                ['libyajl2'],
                ['libyajl-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike\EnterpriseLinux && $this->os->getNumericVersion() >= 10) {
            throw $this->createNotProvidedException();
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['yajl'],
                ['yajl-devel']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
