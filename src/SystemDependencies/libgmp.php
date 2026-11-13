<?php

namespace IPE\SystemDependencies;

use IPE\Filesystem\Command;
use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libgmp extends SystemDependency
{
    /**
     * @var string
     */
    const HEADER = '/usr/include/gmp.h';

    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-gmp';

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
                ['gmp'],
                ['gmp-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                [],
                ['libgmp-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            return new Packages(
                ['libgmp10'],
                ['libgmp-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['gmp'],
                ['gmp-devel']
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
        // The configure script of PHP 5 doesn't look in the multiarch directories
        $fs = $this->os->getFilesystem();
        if (($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) && $this->php->getMajorMinorVersion() < 700 && !$fs->isFile(self::HEADER)) {
            list(, $multiarch) = $fs->run(Command::create('gcc', ['-print-multiarch'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
            $fs->createSymlink('/usr/include/' . trim($multiarch) . '/gmp.h', self::HEADER);
            $state->registerShutdownCallback(static function () use ($fs) {
                $fs->deleteFile(self::HEADER);
            });
        }
    }
}
