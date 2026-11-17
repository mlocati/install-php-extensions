<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency\CompiledLibrary;
use IPE\SystemPackager\Packages;

/**
 * Amazon Linux and Enterprise Linux don't provide libstatgrab.
 */
final class libstatgrab extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-statgrab';

    /**
     * @public
     * @var string
     */
    const VERSION = '0.92.1';

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
            return new Packages(
                ['libstatgrab'],
                ['libstatgrab-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['^libstatgrab[0-9]*(t64)?$'],
                ['libstatgrab-dev']
            );
        }
        if ($this->os instanceof OS\Fedora) {
            return new Packages(
                ['libstatgrab'],
                ['libstatgrab-devel']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages();
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
        return $this->os instanceof OS\Posix\RHELLike && !$this->os instanceof OS\Fedora && parent::needsCompilation();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        $fs = $this->os->getFilesystem();

        return $fs->isFile('/usr/local/include/statgrab.h') && ($fs->isFile('/usr/local/lib/libstatgrab.so') || $fs->isFile('/usr/local/lib64/libstatgrab.so'));
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
        return 'https://github.com/libstatgrab/libstatgrab/releases/download/LIBSTATGRAB_' . str_replace('.', '_', self::VERSION) . '/libstatgrab-' . self::VERSION . '.tar.gz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        // Only the library: the tools need ncurses, the manual pages need docbook2man
        $this->runBuildStep('./configure', ['--disable-saidar', '--disable-statgrab', '--disable-examples', '--disable-man', '--disable-tests'], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs()], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['install'], $sourceDirectory);
    }
}
