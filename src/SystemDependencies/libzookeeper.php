<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency\CompiledLibrary;
use IPE\SystemPackager\Packages;

/**
 * Alpine does not provide the C client of ZooKeeper, and compiling it requires Maven.
 */
final class libzookeeper extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-zookeeper';

    /**
     * @public
     * @var string
     */
    const VERSION = '3.9.4';

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
        if ($this->os instanceof OS\Alpine) {
            if ($this->isInstalled()) {
                return new Packages();
            }

            return new Packages(
                [],
                ['maven', 'automake', 'libtool', 'openjdk8']
            );
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return new Packages(
                ['^libzookeeper-mt2(t64)?$'],
                ['libzookeeper-mt-dev']
            );
        }

        throw $this->createUnknownPackagesException();
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
        return $this->os->getFilesystem()->isFile('/usr/local/include/zookeeper/zookeeper.h');
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
        $version = self::VERSION;

        return "https://archive.apache.org/dist/zookeeper/zookeeper-{$version}/apache-zookeeper-{$version}.tar.gz";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $this->runBuildStep('mvn', ['-pl', 'zookeeper-jute', 'compile'], $sourceDirectory);
        $clientDirectory = "{$sourceDirectory}/zookeeper-client/zookeeper-client-c";
        $this->runBuildStep('autoreconf', ['-if'], $clientDirectory);
        $this->runBuildStep('./configure', ['--without-cppunit'], $clientDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs(), 'CFLAGS=-Wno-stringop-truncation -Wno-format-overflow'], $clientDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['install'], $clientDirectory);
    }
}
