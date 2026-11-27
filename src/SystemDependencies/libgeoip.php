<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libgeoip extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-geoip';

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
                ['geoip'],
                ['geoip-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libgeoip1[0-9]*$'],
                ['libgeoip-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            // Enterprise Linux 9+ doesn't provide GeoIP, but the remi-safe repository of Remi does
            return new Packages(
                ['GeoIP'],
                ['GeoIP-devel']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            // Homebrew dropped the legacy GeoIP library
            throw $this->createNotProvidedException();
        }

        throw $this->createUnknownPackagesException();
    }
}
