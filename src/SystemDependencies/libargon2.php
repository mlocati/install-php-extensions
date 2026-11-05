<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libargon2 extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-argon2';

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
            if ($this->os->getNumericVersion() < 308) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['argon2-libs'],
                ['argon2-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            $version = $this->os->getNumericVersion();
            if ($version <= OS\Debian::VERSION_JESSIE) {
                throw $this->createNotProvidedException();
            }
            if ($version <= OS\Debian::VERSION_STRETCH) {
                return new Packages(
                    ['libargon2-0'],
                    ['libargon2-0-dev']
                );
            }

            return new Packages(
                ['libargon2-1'],
                ['libargon2-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
