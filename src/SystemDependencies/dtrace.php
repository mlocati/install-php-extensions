<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

/**
 * The dtrace command and the sys/sdt.h header, to compile the DTrace probes of the extensions.
 */
final class dtrace extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'dtrace';

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
            throw $this->createNotProvidedException();
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                [],
                ['systemtap-sdt-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike\EnterpriseLinux && $this->os->getNumericVersion() <= 8) {
            return new Packages(
                [],
                ['systemtap-sdt-devel']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                [],
                ['systemtap-sdt-devel', 'systemtap-sdt-dtrace']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
