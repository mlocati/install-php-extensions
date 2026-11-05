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
        if ($this->os instanceof OS\Alpine) {
            return new Packages(
                ['libmcrypt'],
                ['libmcrypt-dev']
            );
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                ['libmcrypt4'],
                ['libmcrypt-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
