<?php

namespace IPE\SystemDependencies;

use IPE\Installer\BuildOptions;
use IPE\SystemDependency\CompiledLibrary;

class libaom extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-aom';

    /**
     * @public
     * @var string
     */
    const VERSION = '3.12.0';

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
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        return $this->buildTools->hasPkgConfigModule('aom');
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getBuildDependencies()
     */
    protected function getBuildDependencies()
    {
        return [cmake::HANDLE, nasm::HANDLE];
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
        return 'https://aomedia.googlesource.com/aom/+archive/v' . self::VERSION . '.tar.gz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        // The source code already contains a "build" directory
        $buildDirectory = $this->createBuildDirectory($sourceDirectory, 'my.build');
        $this->runBuildStep('cmake', [
            '-DCMAKE_BUILD_TYPE=' . ($this->buildOptions->getType() === BuildOptions::TYPE_DEBUG ? 'Debug' : 'Release'),
            '-DBUILD_SHARED_LIBS=1',
            '-DENABLE_DOCS=0',
            '-DENABLE_EXAMPLES=0',
            '-DENABLE_TESTDATA=0',
            '-DENABLE_TESTS=0',
            '-DENABLE_TOOLS=0',
            '-DCMAKE_INSTALL_LIBDIR:PATH=lib',
            '..',
        ], $buildDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs(), 'install'], $buildDirectory);
    }
}
