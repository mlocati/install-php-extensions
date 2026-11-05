<?php

namespace IPE\Test\Support\SystemDependencyTest;

use IPE\SystemDependency;

/**
 * Builds a cycle together with CircularDependencyB.
 */
final class CircularDependencyA extends SystemDependency
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\DeclaresHandle::getHandle()
     */
    public static function getHandle()
    {
        return 'fake-a';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getRequiredDependencies()
     */
    public function getRequiredDependencies()
    {
        return [CircularDependencyB::getHandle()];
    }
}
