<?php

namespace IPE\SystemDependencies;

use IPE\SystemDependency\CompiledLibrary;

final class libtomcrypt extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-tomcrypt';

    /**
     * @public
     * @var string
     */
    const VERSION = '1.18.2';

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

        return $fs->isFile('/usr/local/lib/libtomcrypt.a') && $fs->isFile('/usr/local/include/tomcrypt.h');
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
        return 'https://github.com/libtom/libtomcrypt/releases/download/v' . self::VERSION . '/crypt-' . self::VERSION . '.tar.xz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $this->runBuildStep('sh', ['-c', 'CFLAGS=-fPIC exec make -j' . $this->getParallelJobs() . ' install'], $sourceDirectory);
    }
}
