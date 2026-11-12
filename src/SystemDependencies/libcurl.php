<?php

namespace IPE\SystemDependencies;

use IPE\Filesystem\Command;
use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libcurl extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-curl';

    /**
     * @var string
     */
    const HEADERS_DIRECTORY = '/usr/include/curl';

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
                ['libcurl'],
                ['curl-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            $version = $this->os->getNumericVersion();
            if ($version <= OS\Debian::STRETCH) {
                return new Packages(
                    ['libcurl3'],
                    ['libcurl4-openssl-dev']
                );
            }
            if ($version <= OS\Debian::BOOKWORM) {
                return new Packages(
                    ['libcurl4'],
                    ['libcurl4-openssl-dev']
                );
            }

            return new Packages(
                ['libcurl4t64'],
                ['libcurl4-openssl-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            $version = $this->os->getNumericVersion();
            if ($version <= OS\Ubuntu::XENIAL) {
                return new Packages(
                    ['libcurl3'],
                    ['libcurl4-openssl-dev']
                );
            }
            if ($version <= OS\Ubuntu::MANTIC) {
                return new Packages(
                    ['libcurl4'],
                    ['libcurl4-openssl-dev']
                );
            }

            return new Packages(
                ['libcurl4t64'],
                ['libcurl4-openssl-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            // dnf needs libcurl (or libcurl-minimal), so it is always installed
            return new Packages(
                [],
                ['libcurl-devel']
            );
        }

        throw $this->createUnknownPackagesException();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::install()
     */
    public function install(State $state)
    {
        // Before PHP 7.4 some configure scripts (curl, solr, http) don't look in the multiarch directories
        $fs = $this->os->getFilesystem();
        if (($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) && $this->php->getMajorMinorVersion() < 704 && !$fs->exists(self::HEADERS_DIRECTORY)) {
            list(, $multiarch) = $fs->run(Command::create('gcc', ['-print-multiarch'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
            $fs->createSymlink('/usr/include/' . trim($multiarch) . '/curl', self::HEADERS_DIRECTORY);
            $state->registerShutdownCallback(static function () use ($fs) {
                $fs->deleteFile(self::HEADERS_DIRECTORY);
            });
        }
    }
}
