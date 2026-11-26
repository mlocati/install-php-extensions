<?php

namespace IPE\SystemDependencies;

use IPE\OS;
use IPE\SystemDependency\CompiledLibrary;

final class libxdiff extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-xdiff';

    /**
     * @public
     * @var string
     */
    const VERSION = '0.23';

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
     * Get the prefix where the library is installed (empty string for the default one).
     *
     * @throws \IPE\Exception\IO
     *
     * @return string
     */
    public static function getInstallPrefix(OS $os)
    {
        if ($os instanceof OS\MacOS) {
            $prefix = $os->getSystemPackager()->getPrefix();
            if ($prefix !== '') {
                // The default prefix (/usr/local) may not be writable
                return "{$prefix}/libxdiff";
            }
        }

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        $fs = $this->os->getFilesystem();
        $prefix = self::getInstallPrefix($this->os);
        if ($prefix !== '') {
            return $fs->isFile("{$prefix}/lib/libxdiff.a");
        }

        return $fs->isFile('/usr/local/lib/libxdiff.a') || $fs->isFile('/usr/local/lib/libxdiff.so') || $fs->isFile('/usr/lib/libxdiff.so');
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
        return 'https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/assets/resources/libxdiff-' . self::VERSION . '.tar.gz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $arguments = ['--disable-shared', '--disable-dependency-tracking', '--with-pic'];
        $prefix = self::getInstallPrefix($this->os);
        if ($prefix !== '') {
            $arguments[] = "--prefix={$prefix}";
        }
        $this->runBuildStep('./configure', $arguments, $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs()], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['install'], $sourceDirectory);
    }
}
