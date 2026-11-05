<?php

namespace IPE\SystemDependencies;

use IPE\SystemDependency\CompiledLibrary;

final class libtommath extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-tommath';

    /**
     * @public
     * @var string
     */
    const VERSION = '1.3.0';

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
        $fs = $this->os->getFilesystem();

        return $fs->isFile('/usr/local/lib/libtommath.a') && $fs->isFile('/usr/local/include/tommath.h');
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getBuildDependencies()
     */
    protected function getBuildDependencies()
    {
        return [cmake::HANDLE];
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
        return 'https://github.com/libtom/libtommath/releases/download/v' . self::VERSION . '/ltm-' . self::VERSION . '.tar.xz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $buildDirectory = $this->createBuildDirectory($sourceDirectory);
        $this->runBuildStep('cmake', ['-DCMAKE_BUILD_TYPE=Release', '-DCMAKE_C_FLAGS=-fPIC', '..'], $buildDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs(), 'install'], $buildDirectory);
    }
}
