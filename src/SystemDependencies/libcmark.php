<?php

namespace IPE\SystemDependencies;

use IPE\OS;
use IPE\SystemDependency\CompiledLibrary;

final class libcmark extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-cmark';

    /**
     * @public
     * @var string
     */
    const VERSION = '0.31.2';

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

        return $fs->isFile('/usr/local/lib/libcmark.so') || $fs->isFile('/usr/local/lib64/libcmark.so') || $fs->isFile('/usr/lib/libcmark.so') || $fs->isFile('/usr/lib64/libcmark.so') || $fs->isFile('/lib/libcmark.so');
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
        return $this->getVersionToBeCompiled();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getSourceURL()
     */
    protected function getSourceURL()
    {
        return 'https://github.com/commonmark/cmark/archive/' . $this->getVersionToBeCompiled() . '.tar.gz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs(), 'cmake_build'], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['install'], $sourceDirectory);
        // The linker of Alpine doesn't look in lib64
        $this->runBuildStep('sh', ['-c', 'if [ -d /usr/local/lib64 ]; then ln -sf /usr/local/lib64/libcmark.so* /usr/local/lib/; fi'], $sourceDirectory);
    }

    /**
     * The newest versions require a cmake newer than the one of old operating systems.
     *
     * @return non-empty-string
     */
    private function getVersionToBeCompiled()
    {
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::VERSION_JESSIE) {
                return '0.29.0';
            }
            if ($this->os->getNumericVersion() <= OS\Debian::VERSION_BUSTER) {
                return '0.31.1';
            }
        }
        if ($this->os instanceof OS\Alpine && $this->os->getNumericVersion() <= 310) {
            return '0.31.1';
        }

        return self::VERSION;
    }
}
