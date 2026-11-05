<?php

namespace IPE\Test\Support\SystemDependencyTest;

use IPE\Installer\State;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

/**
 * A build tool: a volatile package, and a log of the phases it went through.
 */
final class FakeTool extends SystemDependency
{
    /**
     * @var list<string>
     */
    public $log = [];

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getHandle()
     */
    public static function getHandle()
    {
        return 'fake-tool';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        return new Packages([], ['fake-tool-pkg']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::install()
     */
    public function install(State $state)
    {
        $this->log[] = 'install';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::finalize()
     */
    public function finalize(State $state)
    {
        $this->log[] = 'finalize';
    }
}
