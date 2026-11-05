<?php

namespace IPE\Filesystem;

use IPE\Filesystem;

final class VolatileDirectory
{
    /**
     * @var non-empty-string
     */
    private $path;

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var non-negative-int
     */
    private $autosubdirCount = 0;

    /**
     * @param non-empty-string $path
     */
    public function __construct($path, Filesystem $fs)
    {
        $this->path = $path;
        $this->fs = $fs;
    }

    public function __destruct()
    {
        try {
            $this->fs->deleteDirectory($this->path);
        } catch (\Exception $_) {
        }
    }

    /**
     * @return non-empty-string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return \IPE\Filesystem
     */
    public function getFilesystem()
    {
        return $this->fs;
    }

    /**
     * Create an empty directory inside this volatile directory and returns its full path.
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    public function createSubdir()
    {
        while (true) {
            $path = $this->getPath() . $this->fs->getDirectorySeparator() . 's' . ++$this->autosubdirCount;
            if (!$this->fs->exists($path)) {
                break;
            }
        }
        $this->fs->createDirectory($path);

        return $path;
    }
}
