<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class librdkafka extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-rdkafka';

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
                ['librdkafka'],
                ['librdkafka-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['librdkafka1'],
                ['librdkafka-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['librdkafka'],
                ['librdkafka-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['librdkafka']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
