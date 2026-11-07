<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libenchant2 extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-enchant-2';

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
     * Does the operating system provide enchant 2 (and no more enchant 1)?
     *
     * @return bool
     */
    public static function isProvidedByOS(OS $os)
    {
        if ($os instanceof OS\Alpine) {
            return $os->getNumericVersion() >= 312;
        }
        if ($os instanceof OS\Debian) {
            return $os->getNumericVersion() >= OS\Debian::BULLSEYE;
        }
        if ($os instanceof OS\Ubuntu) {
            return $os->getNumericVersion() >= OS\Ubuntu::FOCAL;
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
                ['enchant2'],
                ['enchant2-dev']
            );
        }

        return new Packages(
            ['libenchant-2-2'],
            ['libenchant-2-dev']
        );
    }
}
