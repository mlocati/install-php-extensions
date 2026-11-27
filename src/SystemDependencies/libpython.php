<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libpython extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-python';

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
                ['python3'],
                ['python3-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libpython3\.[0-9]+(t64)?$'],
                ['python3-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            if ($this->os instanceof OS\Posix\RHELLike\EnterpriseLinux && $this->os->getNumericVersion() <= 8) {
                // The default Python of Enterprise Linux 8 is 3.6, which is too old
                return new Packages(
                    ['python3.12-libs'],
                    ['python3.12-devel']
                );
            }

            return new Packages(
                ['python3-libs'],
                ['python3-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            // An alias of the most recent versioned formula (like python@3.15)
            return new Packages(
                ['python3']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
