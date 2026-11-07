<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libldap extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-ldap';

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
                ['libldap'],
                ['openldap-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::VERSION_JESSIE) {
                return new Packages(
                    [],
                    ['libldap2-dev']
                );
            }

            return new Packages(
                ['libldap-common'],
                ['libldap2-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::TRUSTY) {
                // The php_config.h of the PHP package enables the SASL support
                return new Packages(
                    ['libldap-2.4-2'],
                    ['libldap2-dev', 'libsasl2-dev']
                );
            }
            if ($this->os->getNumericVersion() <= OS\Ubuntu::XENIAL) {
                return new Packages(
                    ['libldap-2.4-2'],
                    ['libldap2-dev']
                );
            }
            if ($this->os->getNumericVersion() <= OS\Ubuntu::FOCAL) {
                return new Packages(
                    ['libldap-common', 'libldap-2.4-2'],
                    ['libldap2-dev']
                );
            }
            if ($this->os->getNumericVersion() <= OS\Ubuntu::JAMMY) {
                return new Packages(
                    ['libldap-common', 'libldap-2.5-0'],
                    ['libldap2-dev']
                );
            }

            return new Packages(
                ['libldap-common', 'libldap2'],
                ['libldap2-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
