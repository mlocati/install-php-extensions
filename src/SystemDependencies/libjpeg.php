<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libjpeg extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-jpeg';

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
                ['libjpeg-turbo'],
                ['libjpeg-turbo-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['libjpeg62-turbo'],
                ['libjpeg62-turbo-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
