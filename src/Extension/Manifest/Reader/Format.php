<?php

namespace IPE\Extension\Manifest\Reader;

use IPE\Exception;
use IPE\Filesystem;

/**
 * Reads the extension metadata from a specific kind of manifest file.
 */
abstract class Format
{
    /**
     * @var \IPE\Logger
     */
    protected $logger;

    public function __construct(\IPE\Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get the names of the files that may contain the manifest, in order of preference.
     *
     * @return non-empty-list<non-empty-string>
     */
    abstract public function getFileNames();

    /**
     * @param string $content the contents of the manifest file
     *
     * @throws \IPE\Exception\InvalidArgument if the contents are malformed
     *
     * @return \IPE\Extension\Manifest|null null if the file doesn't describe a PHP extension
     */
    abstract public function parse($content);

    /**
     * Look for a manifest file in a directory containing the extension source code.
     *
     * @param non-empty-string $directory
     *
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Extension\Manifest|null
     */
    public function readFromDirectory($directory, Filesystem $fs)
    {
        foreach ($this->getFileNames() as $file) {
            $path = $directory . $fs->getDirectorySeparator() . $file;
            if (!$fs->isFile($path)) {
                continue;
            }
            $this->logger->debug("Reading {$file}");
            try {
                $manifest = $this->parse($fs->readFile($path));
            } catch (Exception\InvalidArgument $x) {
                $this->logger->warning("Ignoring invalid {$file} file: {$x->getMessage()}", ['file' => $path]);
                continue;
            }
            if ($manifest !== null) {
                return $manifest;
            }
            $this->logger->debug("{$file} doesn't describe a PHP extension");
        }

        return null;
    }
}
