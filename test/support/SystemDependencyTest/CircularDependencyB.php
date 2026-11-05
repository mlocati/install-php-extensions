<?php

namespace IPE\Test\Support\SystemDependencyTest;

use IPE\SystemDependency;

/**
 * Builds a cycle together with CircularDependencyA.
 */
final class CircularDependencyB extends SystemDependency
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\DeclaresHandle::getHandle()
     */
    public static function getHandle()
    {
        return 'fake-b';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getRequiredDependencies()
     */
    public function getRequiredDependencies()
    {
        return [CircularDependencyA::getHandle()];
    }
}
