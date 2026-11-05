<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libstatgrab extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-statgrab';

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
                ['libstatgrab'],
                ['libstatgrab-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['^libstatgrab[0-9]*(t64)?$'],
                ['libstatgrab-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
