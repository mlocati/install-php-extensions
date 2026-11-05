<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

/**
 * It provides the strip command, for extensions that don't need the compilation tools.
 */
final class binutils extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'binutils';

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
        return new Packages(
            [],
            ['binutils']
        );
    }
}
