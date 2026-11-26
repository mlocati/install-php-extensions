<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libxslt extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-xslt';

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
            // On some versions (for example 3.10) libexslt links to libgcrypt, but libxslt-dev doesn't depend on libgcrypt-dev
            return new Packages(
                ['libxslt'],
                ['libxslt-dev', 'libgcrypt-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['libxslt1.1'],
                ['libxslt-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libxslt'],
                ['libxslt-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['libxslt']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
