<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libtokyocabinet extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-tokyocabinet';

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
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libtokyocabinet9(t64)?$'],
                ['libtokyocabinet-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['tokyocabinet'],
                ['tokyocabinet-devel']
            );
        }

        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['tokyo-cabinet']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
