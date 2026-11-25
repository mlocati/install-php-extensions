<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency\CompiledLibrary;
use IPE\SystemPackager\Packages;

/**
 * Alpine, Amazon Linux, Fedora and Enterprise Linux 10+ don't provide libgearman.
 */
final class libgearman extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-gearman';

    /**
     * @public
     * @var string
     */
    const VERSION = '2.2.0';

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
                return new Packages(['libstdc++', 'libuuid']);
            }

            return new Packages(
                ['libstdc++', 'libuuid'],
                ['boost-dev', 'gperf', 'libmemcached-dev', 'libevent-dev', 'util-linux-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libgearman[0-9]*(t64)?$'],
                ['libgearman-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            if ($this->isInEPEL()) {
                return new Packages(
                    ['libgearman'],
                    ['libgearman-devel']
                );
            }
            if ($this->isInstalled()) {
                return new Packages(['libstdc++', 'libuuid']);
            }

            return new Packages(
                ['libstdc++', 'libuuid'],
                ['boost-devel', 'gperf', 'libevent-devel', 'libuuid-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            return new Packages(
                ['gearman']
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
        return ($this->os instanceof OS\Alpine || ($this->os instanceof OS\Posix\RHELLike && !$this->isInEPEL())) && parent::needsCompilation();
    }

    /**
     * @return bool
     */
    private function isInEPEL()
    {
        // Fedora retired gearmand, and EPEL 10 doesn't include it
        return $this->os instanceof OS\Posix\RHELLike\EnterpriseLinux && $this->os->getNumericVersion() <= 9;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        $fs = $this->os->getFilesystem();

        return $fs->isFile('/usr/local/include/libgearman/gearman.h') && $fs->isFile('/usr/local/lib/libgearman.so');
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
        return 'https://github.com/gearman/gearmand/releases/download/' . self::VERSION . '/gearmand-' . self::VERSION . '.tar.gz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $this->runBuildStep('./configure', [], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs(), 'install-binPROGRAMS'], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs(), 'install-nobase_includeHEADERS'], $sourceDirectory);
    }
}
