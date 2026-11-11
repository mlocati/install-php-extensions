<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libfreetype2 extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-freetype2';

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
                ['freetype'],
                ['freetype-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::BUSTER) {
                return new Packages(
                    ['libfreetype6'],
                    ['libfreetype6-dev']
                );
            }

            return new Packages(
                ['libfreetype6'],
                ['libfreetype-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::BIONIC) {
                return new Packages(
                    ['libfreetype6'],
                    ['libfreetype6-dev']
                );
            }

            return new Packages(
                ['libfreetype6'],
                ['libfreetype-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
