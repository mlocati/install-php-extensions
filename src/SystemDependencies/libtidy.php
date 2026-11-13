<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libtidy extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-tidy';

    /**
     * @var string
     */
    const OLD_HEADER = '/usr/include/buffio.h';

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
            if ($this->os->getNumericVersion() <= 314) {
                return new Packages(
                    ['tidyhtml-libs'],
                    ['tidyhtml-dev']
                );
            }

            return new Packages(
                ['tidyhtml'],
                ['tidyhtml-dev']
            );
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                ['^libtidy-?[0-9][0-9.\-]*(deb[0-9])?$'],
                ['libtidy-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libtidy'],
                ['libtidy-devel']
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
        // Before PHP 7.3 the configure script only looks for the old name of the header
        $fs = $this->os->getFilesystem();
        if ($this->os instanceof OS\Alpine && $this->php->getMajorMinorVersion() < 703 && !$fs->isFile(self::OLD_HEADER)) {
            $fs->createSymlink('/usr/include/tidybuffio.h', self::OLD_HEADER);
            $state->registerShutdownCallback(static function () use ($fs) {
                $fs->deleteFile(self::OLD_HEADER);
            });
        }
    }
}
