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
        if ($this->os instanceof OS\Posix\AlpineLike) {
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
        if ($this->os instanceof OS\Posix\RHELLike) {
            // The PHP packages use the PCRE library of the system, and PHP switched to PCRE2 in 7.3
            if ($this->php->getMajorMinorVersion() <= 702) {
                return new Packages(
                    ['pcre'],
                    ['pcre-devel']
                );
            }

            return new Packages(
                ['pcre2'],
                ['pcre2-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            // The PHP formulae use PCRE2 since PHP 7.3, before they use the bundled PCRE
            if ($this->php->getMajorMinorVersion() <= 702) {
                return new Packages();
            }

            return new Packages(
                ['pcre2']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
