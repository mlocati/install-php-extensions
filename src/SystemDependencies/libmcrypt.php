<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libmcrypt extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-mcrypt';

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
                ['libmcrypt'],
                ['libmcrypt-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['libmcrypt4'],
                ['libmcrypt-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike\EnterpriseLinux && $this->os->getNumericVersion() >= 10) {
            throw $this->createNotProvidedException();
        }
        if ($this->os instanceof OS\AmazonLinux) {
            throw $this->createNotProvidedException();
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libmcrypt'],
                ['libmcrypt-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            throw $this->createNotProvidedException();
        }

        throw $this->createUnknownPackagesException();
    }
}
