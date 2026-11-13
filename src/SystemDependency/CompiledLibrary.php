<?php

namespace IPE\SystemDependency;

use IPE\Exception;
use IPE\Filesystem\ArchiveExtractor;
use IPE\Filesystem\Command;
use IPE\Filesystem\Downloader;
use IPE\Installer\BuildOptions;
use IPE\Installer\BuildTools;
use IPE\Installer\State;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependency;

/**
 * A library that must be compiled because the operating system doesn't provide it (or provides a version that's too old).
 */
abstract class CompiledLibrary extends SystemDependency
{
    /**
     * @private
     * @var string
     */
    const RHELLIKE_LINKER_CONFIG = '/etc/ld.so.conf.d/usr-local.conf';

    /**
     * @var \IPE\Logger
     */
    protected $logger;

    /**
     * @var \IPE\Filesystem\Downloader
     */
    private $downloader;

    /**
     * @var \IPE\Filesystem\ArchiveExtractor
     */
    private $archiveExtractor;

    /**
     * @var \IPE\Installer\BuildTools
     */
    protected $buildTools;

    /**
     * @var \IPE\Installer\BuildOptions
     */
    protected $buildOptions;

    /**
     * @var string
     */
    private $sourceURL = '';

    public function __construct(PHPInfo $php, OS\Posix $os, Values $options, Downloader $downloader, ArchiveExtractor $archiveExtractor, BuildTools $buildTools, BuildOptions $buildOptions, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        parent::__construct($php, $os, $options);
        $this->downloader = $downloader;
        $this->archiveExtractor = $archiveExtractor;
        $this->buildTools = $buildTools;
        $this->buildOptions = $buildOptions;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getRequiredDependencies()
     */
    public function getRequiredDependencies()
    {
        return $this->needsCompilation() ? $this->getBuildDependencies() : [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::install()
     */
    public function install(State $state)
    {
        if (!$this->needsCompilation()) {
            return;
        }
        $this->logger->info('Compiling ' . static::getHandle());
        $this->build($this->fetchSource($state));
        $this->sourceURL = $this->getSourceURL();
        $this->refreshLinkerCache();
    }

    /**
     * Let the system linker find the libraries installed in /usr/local.
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    protected function refreshLinkerCache()
    {
        $fs = $this->os->getFilesystem();
        // The linker of RHEL-like systems doesn't look in /usr/local
        if ($this->os instanceof OS\Posix\RHELLike && !$fs->exists(self::RHELLIKE_LINKER_CONFIG)) {
            $fs->writeFile(self::RHELLIKE_LINKER_CONFIG, "/usr/local/lib\n/usr/local/lib64\n");
        }
        // The library may be in a directory where the system linker doesn't look until its cache is refreshed: it's not an error if we can't do it
        $fs->run(Command::create('ldconfig')->withOutput(Command::OUTPUT_DISCARD));
    }

    /**
     * Get the URL of the source code compiled by install() (empty string if the library has not been compiled).
     *
     * @return string
     */
    public function getCompiledSourceURL()
    {
        return $this->sourceURL;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    protected function needsCompilation()
    {
        return !$this->isInstalled();
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    abstract protected function isInstalled();

    /**
     * Get the handles of the system dependencies needed to compile the library.
     *
     * @return list<non-empty-string>
     */
    abstract protected function getBuildDependencies();

    /**
     * @return non-empty-string
     */
    abstract protected function getSourceURL();

    /**
     * Get the version of the library that install() compiles.
     *
     * @return non-empty-string
     */
    abstract public function getCompiledVersion();

    /**
     * @param non-empty-string $sourceDirectory
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    abstract protected function build($sourceDirectory);

    /**
     * Create an empty directory where the library can be built.
     *
     * @param non-empty-string $sourceDirectory
     * @param non-empty-string $name
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    protected function createBuildDirectory($sourceDirectory, $name = 'build')
    {
        $result = "{$sourceDirectory}/{$name}";
        $this->os->getFilesystem()->createDirectory($result);

        return $result;
    }

    /**
     * @param non-empty-string $executable
     * @param list<string> $arguments
     * @param non-empty-string $workingDirectory
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    protected function runBuildStep($executable, array $arguments, $workingDirectory)
    {
        list($rc) = $this->os->getFilesystem()->run(Command::create($executable, $arguments)->withWorkingDirectory($workingDirectory)->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\IO('Failed to compile ' . static::getHandle() . ": {$executable} failed with exit code {$rc}");
        }
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return positive-int
     */
    protected function getParallelJobs()
    {
        return $this->buildTools->getParallelJobs($this->buildOptions);
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     *
     * @return non-empty-string the directory containing the source code
     */
    private function fetchSource(State $state)
    {
        $url = $this->getSourceURL();
        $this->logger->info("Downloading {$url}");
        $downloadDirectory = $state->getVolatileDir()->createSubdir();
        $archive = "{$downloadDirectory}/source";
        $this->downloader->saveAs($url, $archive);
        $sourceDirectory = $this->archiveExtractor->extractTo($archive, $state->getVolatileDir()->createSubdir());
        $this->os->getFilesystem()->deleteDirectory($downloadDirectory);

        return $sourceDirectory;
    }
}
