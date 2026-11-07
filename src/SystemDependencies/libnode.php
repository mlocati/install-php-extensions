<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libnode extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-node';

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
                ['libstdc++', 'nodejs-libs'],
                ['nodejs-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::VERSION_STRETCH) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['^libnode[0-9]+$'],
                ['libnode-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::BIONIC) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['^libnode[0-9]+$'],
                ['libnode-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
