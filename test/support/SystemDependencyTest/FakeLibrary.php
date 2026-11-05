<?php

namespace IPE\Test\Support\SystemDependencyTest;

use IPE\Installer\State;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

/**
 * A library that needs the fake tool to be compiled.
 */
final class FakeLibrary extends SystemDependency
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getHandle()
     */
    public static function getHandle()
    {
        return 'lib-fake';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getRequiredDependencies()
     */
    public function getRequiredDependencies()
    {
        return ['fake-tool', 'not-registered'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        return new Packages(['libfake'], ['libfake-dev']);
    }
}
