<?php

namespace IPE\SystemDependencies;

use IPE\OS;
use IPE\SystemDependency\CompiledLibrary;

/**
 * musl does not provide the functions of libxcrypt.
 */
final class libxcrypt extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-xcrypt';

    /**
     * @public
     * @var string
     */
    const VERSION = '4.5.2';

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
     * @see \IPE\SystemDependency\CompiledLibrary::needsCompilation()
     */
    protected function needsCompilation()
    {
        return $this->os instanceof OS\Alpine && parent::needsCompilation();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        $fs = $this->os->getFilesystem();

        return $fs->isFile('/usr/local/lib/libcrypt.so') || $fs->isFile('/usr/lib/libcrypt.so');
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getBuildDependencies()
     */
    protected function getBuildDependencies()
    {
        return [linuxheaders::HANDLE];
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
        return 'https://github.com/besser82/libxcrypt/releases/download/v' . self::VERSION . '/libxcrypt-' . self::VERSION . '.tar.xz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $this->runBuildStep('./configure', ['--prefix', '/usr', '--disable-werror'], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs()], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['install'], $sourceDirectory);
    }
}
