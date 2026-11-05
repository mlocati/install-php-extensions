<?php

namespace IPE\SystemDependencies;

use IPE\Filesystem\Command;
use IPE\Installer\State;
use IPE\OS;
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
                return $this->getOldAlpinePackages();
            }

            return new Packages(
                ['libssl3'],
                ['openssl-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                [$this->getDebianPackage($this->os)],
                ['libssl-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }

    /**
     * The older versions of Alpine may use different SSL libraries: we need the one used by PHP.
     *
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\SystemPackager\Packages
     */
    private function getOldAlpinePackages()
    {
        list(, $output) = $this->os->getFilesystem()->run(Command::create($this->php->getPHPBin(), ['-d', 'display_errors=stderr', '-r', 'echo defined("OPENSSL_VERSION_TEXT") ? OPENSSL_VERSION_TEXT : "";'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        $matches = null;
        if (preg_match('/^OpenSSL (?<major>\d+)\.(?<minor>\d+)/', $output, $matches)) {
            return new Packages(
                ['libssl' . ((int) $matches['major'] <= 1 ? "{$matches['major']}.{$matches['minor']}" : $matches['major'])],
                ['openssl-dev']
            );
        }
        if (preg_match('/^LibreSSL (?<version>\d+\.\d+)/', $output, $matches)) {
            return new Packages(
                ["libressl{$matches['version']}-libssl"],
                ['libressl-dev']
            );
        }

        return new Packages();
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
