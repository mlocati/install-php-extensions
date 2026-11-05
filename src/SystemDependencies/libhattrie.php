<?php

namespace IPE\SystemDependencies;

use IPE\SystemDependency\CompiledLibrary;

/**
 * A header-only library.
 */
final class libhattrie extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-hattrie';

    /**
     * @public
     * @var string
     */
    const VERSION = '0.7.1';

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
        return $this->os->getFilesystem()->isFile('/usr/local/include/hat-trie/include/tsl/htrie_map.h');
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
        return 'https://codeload.github.com/Tessil/hat-trie/tar.gz/v' . self::VERSION;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $this->runBuildStep('mkdir', ['-p', '/usr/local/include/hat-trie'], $sourceDirectory);
        $this->runBuildStep('cp', ['-R', 'include', '/usr/local/include/hat-trie/'], $sourceDirectory);
    }
}
