<?php

namespace IPE\SystemDependencies;

use IPE\SystemDependency\CompiledLibrary;

final class libip2location extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-ip2location';

    /**
     * @public
     * @var string
     */
    const VERSION = '8.7.0';

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

        return $fs->isFile('/usr/local/lib/libIP2Location.so') && $fs->isFile('/usr/local/include/IP2Location.h');
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getBuildDependencies()
     */
    protected function getBuildDependencies()
    {
        return [automake::HANDLE, libtool::HANDLE];
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
        return 'https://codeload.github.com/chrislim2888/IP2Location-C-Library/tar.gz/' . self::VERSION;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $this->runBuildStep('autoreconf', ['-fiv'], $sourceDirectory);
        $this->runBuildStep('./configure', [], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs()], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['install'], $sourceDirectory);
    }
}
