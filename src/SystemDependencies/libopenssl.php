<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\PHPInfo\SSLLibrary;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libopenssl extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-openssl';

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
            if ($this->os->getNumericVersion() <= 316) {
                // The older versions of Alpine offer more SSL libraries: we need the one used by PHP
                $library = $this->php->getSSLLibrary();
                if ($library !== null) {
                    return $this->getAlpinePackagesFor($library);
                }
                if ($this->os->getNumericVersion() <= 308) {
                    return new Packages(
                        ['libssl1.0'],
                        ['openssl-dev']
                    );
                }

                return new Packages(
                    ['libssl1.1'],
                    ['openssl-dev']
                );
            }

            return new Packages(
                ['libssl3'],
                ['openssl-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            // The PHP 5 of the Docker images of Debian Stretch uses OpenSSL 1.0, the system uses OpenSSL 1.1
            if ($this->os->getNumericVersion() === OS\Debian::VERSION_STRETCH && $this->isPHPUsing(SSLLibrary::TYPE_OPENSSL, '1.0')) {
                return new Packages(
                    ['libssl1.0.2'],
                    ['libssl1.0-dev']
                );
            }

            return new Packages(
                [$this->getDebianPackage($this->os)],
                ['libssl-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }

    /**
     * @return \IPE\SystemPackager\Packages
     */
    private function getAlpinePackagesFor(SSLLibrary $library)
    {
        if ($library->getType() === SSLLibrary::TYPE_LIBRESSL) {
            return new Packages(
                ["libressl{$library->getMajorMinorVersion()}-libssl"],
                ['libressl-dev']
            );
        }
        if ($library->getMajorVersion() <= 1) {
            return new Packages(
                ["libssl{$library->getMajorMinorVersion()}"],
                ['openssl-dev']
            );
        }

        return new Packages(
            ["libssl{$library->getMajorVersion()}"],
            ['openssl-dev']
        );
    }

    /**
     * @param \IPE\PHPInfo\SSLLibrary::TYPE_* $type
     * @param string $majorMinorVersion
     *
     * @return bool
     */
    private function isPHPUsing($type, $majorMinorVersion)
    {
        $library = $this->php->getSSLLibrary();

        return $library !== null && $library->getType() === $type && $library->getMajorMinorVersion() === $majorMinorVersion;
    }

    /**
     * @return non-empty-string
     */
    private function getDebianPackage(OS\Debian $os)
    {
        $version = $os->getNumericVersion();
        if ($version <= OS\Debian::VERSION_JESSIE) {
            return 'libssl1.0.0';
        }
        if ($version <= OS\Debian::VERSION_BULLSEYE) {
            return 'libssl1.1';
        }

        if ($version <= OS\Debian::VERSION_BOOKWORM) {
            return 'libssl3';
        }

        return '^libssl3(t64)?$';
    }
}
