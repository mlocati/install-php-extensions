<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libwebp extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-webp';

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
            if ($this->php->getMajorMinorVersion() <= 805) {
                return new Packages(
                    ['libwebp'],
                    ['libwebp-dev']
                );
            }

            return new Packages(
                ['libwebp', 'libwebpdemux', 'libwebpmux'],
                ['libwebp-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            if ($this->php->getMajorMinorVersion() <= 805) {
                return new Packages(
                    ['^libwebp[0-9]+$'],
                    ['libwebp-dev']
                );
            }

            return new Packages(
                ['^libwebp[0-9]+$', '^libwebpdemux[0-9]+$', '^libwebpmux[0-9]+$'],
                ['libwebp-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            // libwebp includes libwebpdemux and libwebpmux
            return new Packages(
                ['libwebp'],
                ['libwebp-devel']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['webp']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
