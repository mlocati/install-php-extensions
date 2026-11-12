<?php

namespace IPE;

interface Filesystem
{
    /**
     * @return bool
     */
    public function isLocal();

    /**
     * @return non-empty-string
     */
    public function getDirectorySeparator();

    /**
     * Get the separator of the items in lists like the PATH environment variable.
     *
     * @return non-empty-string
     */
    public function getPathSeparator();

    /**
     * @param non-empty-string $name
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return string the path of the command, without resolving symbolic links: multi-call binaries like BusyBox rely on the name they are called with (empty string if not found)
     */
    public function resolveCommand($name);

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return bool
     */
    public function exists($path);

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return bool
     */
    public function isFile($path);

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return bool
     */
    public function isDirectory($path);

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return bool
     */
    public function isSymlink($path);

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return bool
     */
    public function isReadable($path);

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return bool
     */
    public function isWritable($path);

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return non-negative-int
     */
    public function getLastModificationTimestamp($path);

    /**
     * @param non-empty-string $path
     * @param positive-int $timestamp
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function setLastModificationTimestamp($path, $timestamp);

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return non-negative-int
     */
    public function getFileSize($path);

    /**
     * @param non-empty-string $path
     * @param non-negative-int|null $maxBytes
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return string
     */
    public function readFile($path, $maxBytes = null);

    /**
     * @param non-empty-string $path
     * @param string $content
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function writeFile($path, $content);

    /**
     * @param non-empty-string $from
     * @param non-empty-string $to
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function copyFile($from, $to);

    /**
     * Create a new directory whose full path is $newPath.
     *
     * @param non-empty-string $source
     * @param non-empty-string $newPath
     * @param bool $keepSymlinks keep symlinks/junctions instead of resolving their target
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function copyDirectory($source, $newPath, $keepSymlinks = false);

    /**
     * @param non-empty-string $target
     * @param non-empty-string $link
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function createSymlink($target, $link);

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    public function getRealPath($path);

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string[]
     */
    public function listDirectoryContents($path);

    /**
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    public function getTemporaryDirectory();

    /**
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    public function createTemporaryFile();

    /**
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    public function createTemporaryDirectory();

    /**
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Filesystem\VolatileDirectory
     */
    public function createVolatileDirectory();

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function deleteFile($path);

    /**
     * @param non-empty-string $path
     * @param int $permissions
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function createDirectory($path, $permissions = 0777);

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function deleteDirectory($path);

    /**
     * Overwrite existing fails is allowed, overwrite existing directories is not allowed.
     *
     * @param non-empty-string $from
     * @param non-empty-string $to
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function rename($from, $to);

    /**
     * @param string $argument
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return string
     */
    public function escapeShellArg($argument);

    /**
     * Run a command and wait for its completion.
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return array{int, string} the exit code, and the captured output (empty string if the output of the command is not captured)
     */
    public function run(Filesystem\Command $command);
}
