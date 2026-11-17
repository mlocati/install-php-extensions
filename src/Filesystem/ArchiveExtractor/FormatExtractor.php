<?php

namespace IPE\Filesystem\ArchiveExtractor;

use IPE\Exception;
use IPE\Filesystem;

/**
 * The extractors must keep the modification times of the files: make regenerates the files that look older than their sources (for example, it runs re2c when a .re file is newer than its .c file).
 */
abstract class FormatExtractor
{
    /**
     * @var \IPE\Logger
     */
    protected $logger;

    /**
     * @var \IPE\Filesystem
     * @readonly
     */
    protected $fs;

    /**
     * @throws \IPE\Exception\IO
     */
    protected function __construct(Filesystem $fs, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->fs = $fs;
    }

    /**
     * @param non-empty-string $archive
     * @param non-empty-string $destinationDirectory
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    public function extractTo($archive, $destinationDirectory)
    {
        $archive = $this->fs->getRealPath($archive);
        if (!$this->fs->isFile($archive)) {
            throw new Exception\IO("Unable to find the file '{$archive}'");
        }
        if (!$this->fs->isReadable($archive)) {
            throw new Exception\IO("The file '{$archive}' is not readable");
        }
        $destinationDirectory = $this->fs->getRealPath($destinationDirectory);
        if (!$this->fs->isDirectory($destinationDirectory)) {
            throw new Exception\IO("Unable to find the directory '{$destinationDirectory}'");
        }

        $this->doExtractTo($archive, $destinationDirectory);

        $contents = $this->fs->listDirectoryContents($destinationDirectory);
        if (count($contents) === 1) {
            $childPath = $destinationDirectory . $this->fs->getDirectorySeparator() . $contents[0];
            if ($this->fs->isDirectory($childPath)) {
                return $childPath;
            }
        }

        return $destinationDirectory;
    }

    /**
     * @param non-empty-string $archive the real path to an existing and readable file
     * @param non-empty-string $destinationDirectory the real path to an existing and writable directory
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    protected abstract function doExtractTo($archive, $destinationDirectory);
}
