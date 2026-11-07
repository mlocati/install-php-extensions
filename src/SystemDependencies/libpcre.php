<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libpcre extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-pcre';

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
                ['pcre'],
                ['pcre-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::BOOKWORM) {
                return new Packages(
                    [],
                    ['libpcre3-dev']
                );
            }

            return new Packages(
                [],
                ['libpcre2-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::NOBLE) {
                return new Packages(
                    [],
                    ['libpcre3-dev']
                );
            }

            return new Packages(
                [],
                ['libpcre2-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
