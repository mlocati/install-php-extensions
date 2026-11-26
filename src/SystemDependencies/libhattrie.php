<?php

namespace IPE\SystemDependencies;

use IPE\OS;
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
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string the directory that contains htrie_map.h
     */
    public static function getHeadersDirectory(OS $os)
    {
        return self::getInstallDirectory($os) . '/include/tsl';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        return $this->os->getFilesystem()->isFile(self::getHeadersDirectory($this->os) . '/htrie_map.h');
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
        $installDirectory = self::getInstallDirectory($this->os);
        $this->runBuildStep('mkdir', ['-p', $installDirectory], $sourceDirectory);
        $this->runBuildStep('cp', ['-R', 'include', "{$installDirectory}/"], $sourceDirectory);
    }

    /**
     * On macOS /usr/local may not be writable.
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    private static function getInstallDirectory(OS $os)
    {
        if ($os instanceof OS\MacOS) {
            $prefix = $os->getSystemPackager()->getPrefix();
            if ($prefix !== '') {
                return "{$prefix}/include/hat-trie";
            }
        }

        return '/usr/local/include/hat-trie';
    }
}
