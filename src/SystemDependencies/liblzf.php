<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class liblzf extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-lzf';

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
     * @return bool
     */
    public static function isProvidedByOS(OS $os)
    {
        if ($os instanceof OS\Alpine) {
            return $os->getNumericVersion() >= 316;
        }
        if ($os instanceof OS\Debian) {
            return $os->getNumericVersion() >= OS\Debian::BULLSEYE;
        }
        if ($os instanceof OS\Ubuntu) {
            return $os->getNumericVersion() >= OS\Ubuntu::FOCAL;
        }

        if ($os instanceof OS\AmazonLinux) {
            return false;
        }
        if ($os instanceof OS\Posix\RHELLike) {
            // Enterprise Linux provides it in EPEL
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        if (!self::isProvidedByOS($this->os)) {
            throw $this->createNotProvidedException();
        }
        if ($this->os instanceof OS\Alpine) {
            return new Packages(
                ['liblzf'],
                ['liblzf-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['liblzf1'],
                ['liblzf-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['liblzf'],
                ['liblzf-devel']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
