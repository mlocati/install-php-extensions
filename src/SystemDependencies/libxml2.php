<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libxml2 extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-xml2';

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
                [],
                ['libxml2-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                [],
                ['libxml2-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
