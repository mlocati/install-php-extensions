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
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                ['libxslt1.1'],
                ['libxslt-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
