<?php

namespace IPE\Service;

use IPE\Filesystem;

/**
 * Detects if we are running in an official Docker PHP image.
 */
final class DockerPHPImage
{
    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var bool|null
     */
    private $inUse = null;

    public function __construct(Filesystem $fs)
    {
        $this->fs = $fs;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    public function isInUse()
    {
        if ($this->inUse === null) {
            $this->inUse = true;
            foreach (['docker-php-ext-configure', 'docker-php-ext-enable', 'docker-php-ext-install', 'docker-php-source'] as $command) {
                if ($this->fs->resolveCommand($command) === '') {
                    $this->inUse = false;
                    break;
                }
            }
        }

        return $this->inUse;
    }
}
