<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libpam extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-pam';

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
                ['linux-pam'],
                ['linux-pam-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::VERSION_BOOKWORM) {
                return new Packages(
                    ['libpam0g'],
                    ['libpam0g-dev']
                );
            }

            return new Packages(
                ['libpam0g'],
                // pam.pc requires audit.pc, but libpam0g-dev doesn't depend on libaudit-dev
                ['libpam0g-dev', 'libaudit-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::NOBLE) {
                return new Packages(
                    ['libpam0g'],
                    ['libpam0g-dev']
                );
            }

            return new Packages(
                ['libpam0g'],
                // pam.pc requires audit.pc, but libpam0g-dev doesn't depend on libaudit-dev
                ['libpam0g-dev', 'libaudit-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
