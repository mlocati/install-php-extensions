<?php

namespace IPE\SystemDependencies;

use IPE\Filesystem\Command;
use IPE\Installer\State;
use IPE\SystemDependency\CompiledLibrary;

/**
 * The archives of ion-c don't contain its submodules: we need to clone its repository.
 */
final class libionc extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-ionc';

    /**
     * @public
     * @var string
     */
    const VERSION = '1.1.6';

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
     * @see \IPE\SystemDependency\CompiledLibrary::install()
     */
    public function install(State $state)
    {
        if (!$this->needsCompilation()) {
            return;
        }
        $directory = $state->getVolatileDir()->createSubdir();
        $this->runBuildStep('git', ['clone', '-q', '-c', 'advice.detachedHead=false', '--depth', '1', '--recurse-submodules', '--branch', 'v' . self::VERSION, $this->getSourceURL(), 'ion'], $directory);
        $this->build("{$directory}/ion");
        $this->os->getFilesystem()->run(Command::create('ldconfig')->withOutput(Command::OUTPUT_DISCARD));
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        $fs = $this->os->getFilesystem();

        return $fs->isFile('/usr/local/lib/libionc.so') && $fs->isFile('/usr/local/include/ionc/ion.h');
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getBuildDependencies()
     */
    protected function getBuildDependencies()
    {
        return [cmake::HANDLE, git::HANDLE];
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
        return 'https://github.com/amzn/ion-c.git';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $buildDirectory = $this->createBuildDirectory($sourceDirectory);
        $this->runBuildStep('sh', ['-c', "CFLAGS='-Wno-incompatible-pointer-types -Wno-stringop-overflow -Wno-discarded-qualifiers' exec cmake -DCMAKE_BUILD_TYPE=Release .. -Wno-dev"], $buildDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs(), 'install'], $buildDirectory);
    }
}
