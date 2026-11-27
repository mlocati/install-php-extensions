<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libvips extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-vips';

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
                ['vips'],
                ['vips-dev']
            );
        }
        if ($this->os instanceof OS\Wolfi) {
            // Without libexif-libs, apk satisfies libexif.so.12 with an old libexif package, while libvips-dev pulls in libexif-libs (which would be removed after the installation)
            return new Packages(
                ['libvips', 'libexif-libs'],
                ['libvips-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libvips[0-9]*(t64)?$'],
                ['libvips-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            // Enterprise Linux provides them only in the remi-safe repository of Remi
            return new Packages(
                ['vips'],
                ['vips-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['vips']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
