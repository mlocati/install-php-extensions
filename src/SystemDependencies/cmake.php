<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class cmake extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'cmake';

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
            ['cmake']
        );
    }
}
