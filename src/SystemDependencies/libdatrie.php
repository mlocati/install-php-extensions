<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency\CompiledLibrary;
use IPE\SystemPackager\Packages;

/**
 * Alpine does not provide libdatrie.
 */
final class libdatrie extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-datrie';

    /**
     * @public
     * @var string
     */
    const VERSION = '0.2.14';

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
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['libdatrie']
            );
        }

        return parent::getSystemPackages($state);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::needsCompilation()
     */
    protected function needsCompilation()
    {
        return $this->os instanceof OS\Posix\AlpineLike && parent::needsCompilation();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        $fs = $this->os->getFilesystem();

        return $fs->isFile('/usr/local/lib/libdatrie.so') || $fs->isFile('/usr/lib/libdatrie.so');
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getBuildDependencies()
     */
    protected function getBuildDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getCompiledVersion()
     */
    public function getCompiledVersion()
    {
        return self::VERSION;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getSourceURL()
     */
    protected function getSourceURL()
    {
        return 'https://github.com/tlwg/libdatrie/releases/download/v' . self::VERSION . '/libdatrie-' . self::VERSION . '.tar.xz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $this->runBuildStep('./configure', [], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs()], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['install'], $sourceDirectory);
    }
}
