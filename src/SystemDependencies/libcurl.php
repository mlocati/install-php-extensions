<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libcurl extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-curl';

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
                ['libcurl'],
                ['curl-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            $version = $this->os->getNumericVersion();
            if ($version <= OS\Debian::STRETCH) {
                return new Packages(
                    ['libcurl3'],
                    ['libcurl4-openssl-dev']
                );
            }
            if ($version <= OS\Debian::BOOKWORM) {
                return new Packages(
                    ['libcurl4'],
                    ['libcurl4-openssl-dev']
                );
            }

            return new Packages(
                ['libcurl4t64'],
                ['libcurl4-openssl-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            $version = $this->os->getNumericVersion();
            if ($version <= OS\Ubuntu::XENIAL) {
                return new Packages(
                    ['libcurl3'],
                    ['libcurl4-openssl-dev']
                );
            }
            if ($version <= OS\Ubuntu::MANTIC) {
                return new Packages(
                    ['libcurl4'],
                    ['libcurl4-openssl-dev']
                );
            }

            return new Packages(
                ['libcurl4t64'],
                ['libcurl4-openssl-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
