<?php

namespace IPE\SystemDependencies;

use IPE\Installer\BuildOptions;
use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency\CompiledLibrary;
use IPE\SystemPackager\Packages;

class libyuv extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-yuv';

    /**
     * The upstream project doesn't publish releases: this is a commit hash.
     *
     * @public
     * @var string
     */
    const VERSION = 'd359a9f922af840b043535d43cf9d38b220d102e';

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
        // It's written in C++
        if ($this->os instanceof OS\Alpine) {
            return new Packages(
                ['libstdc++']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages();
        }

        throw $this->createUnknownPackagesException();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        // Its build system doesn't install any pkg-config file
        return $this->buildTools->canLinkLibrary('yuv', 'libyuv.h');
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getBuildDependencies()
     */
    protected function getBuildDependencies()
    {
        // If libjpeg is available it links to it, so it must stay installed
        return [cmake::HANDLE, libjpeg::HANDLE];
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
        return 'https://chromium.googlesource.com/libyuv/libyuv/+archive/' . self::VERSION . '.tar.gz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $buildDirectory = $this->createBuildDirectory($sourceDirectory);
        $this->runBuildStep('cmake', ['-DCMAKE_BUILD_TYPE=' . ($this->buildOptions->getType() === BuildOptions::TYPE_DEBUG ? 'Debug' : 'Release'), '-DCMAKE_INSTALL_PREFIX:PATH=/usr', '..'], $buildDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs(), 'install'], $buildDirectory);
    }
}
