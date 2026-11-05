<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\SystemDependency\CompiledLibrary;
use IPE\SystemPackager\Packages;

/**
 * Operating systems don't provide it.
 */
class libidnkit extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-idnkit';

    /**
     * @public
     * @var string
     */
    const VERSION = '2.3';

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
        if ($this->needsCompilation()) {
            // The source code is published in .tar.bz2 format
            return new Packages(
                [],
                ['bzip2']
            );
        }

        return new Packages();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        // It doesn't install any pkg-config file
        return $this->os->getFilesystem()->isFile('/usr/local/lib/libidnkit.so');
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
        return 'https://jprs.co.jp/idn/idnkit-' . self::VERSION . '.tar.bz2';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        // Recent compilers refuse its code otherwise
        $this->runBuildStep('./configure', ['CFLAGS=-Wno-incompatible-pointer-types'], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs(), 'install'], $sourceDirectory);
    }
}
