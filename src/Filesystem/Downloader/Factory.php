<?php

namespace IPE\Filesystem\Downloader;

use IPE\Exception;
use IPE\Filesystem;

class Factory
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var array{cacheDir?: string, cacheMaxAge?: non-negative-int|non-empty-string, proxy?: string, insecure?: bool}
     */
    private $config;

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @param array{cacheDir?: string, cacheMaxAge?: non-negative-int|non-empty-string, proxy?: string, insecure?: bool} $config
     */
    public function __construct(Filesystem $fs, \IPE\Logger $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->fs = $fs;
        $this->config = $config;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Filesystem\Downloader
     */
    public function buildDownloader()
    {
        $downloader = $this->detectActualDownloader();
        if (!array_key_exists('cacheDir', $this->config) || $this->config['cacheDir'] === '') {
            return $downloader;
        }

        return new Cached($downloader, $this->logger, $this->config['cacheDir'], array_key_exists('cacheMaxAge', $this->config) ? $this->config['cacheMaxAge'] : 0);
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Filesystem\Downloader
     */
    private function detectActualDownloader()
    {
        if (($downloader = WithCurlCommand::createIfAvailable($this->config, $this->fs, $this->logger)) !== null) {
            return $downloader;
        }
        if (($downloader = WithWgetCommand::createIfAvailable($this->config, $this->fs, $this->logger)) !== null) {
            return $downloader;
        }
        if (($downloader = WithCurlExtension::createIfAvailable($this->config, $this->fs, $this->logger)) !== null) {
            return $downloader;
        }
        if (($downloader = WithStreamWrapper::createIfAvailable($this->config, $this->fs, $this->logger)) !== null) {
            return $downloader;
        }

        throw new Exception\IO('No downloader engine detected');
    }
}
