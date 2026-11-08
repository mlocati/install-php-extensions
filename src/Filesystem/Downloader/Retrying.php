<?php

namespace IPE\Filesystem\Downloader;

use IPE\Exception;
use IPE\Filesystem\Downloader;

final class Retrying implements Downloader
{
    /**
     * The default number of seconds to wait before retrying a failed download.
     *
     * @var non-negative-int
     */
    const DEFAULT_DELAY = 5;

    /**
     * @var \IPE\Filesystem\Downloader
     */
    private $actualDownloader;

    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var non-negative-int
     */
    private $retries;

    /**
     * @var non-negative-int
     */
    private $delay;

    /**
     * @param non-negative-int $retries how many times a failed download should be retried
     * @param non-negative-int $delay the number of seconds to wait before retrying a failed download
     */
    public function __construct(Downloader $actualDownloader, \IPE\Logger $logger, $retries, $delay)
    {
        $this->actualDownloader = $actualDownloader;
        $this->logger = $logger;
        $this->retries = $retries;
        $this->delay = $delay;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::saveAs()
     */
    public function saveAs($url, $filename)
    {
        $actualDownloader = $this->actualDownloader;
        $this->run($url, static function () use ($actualDownloader, $url, $filename) {
            $actualDownloader->saveAs($url, $filename);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::getContent()
     */
    public function getContent($url)
    {
        $actualDownloader = $this->actualDownloader;

        return $this->run($url, static function () use ($actualDownloader, $url) {
            return $actualDownloader->getContent($url);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::getFilesystem()
     */
    public function getFilesystem()
    {
        return $this->actualDownloader->getFilesystem();
    }

    /**
     * @template T
     *
     * @param string $url
     * @param callable(): T $download
     *
     * @throws \IPE\Exception\IO
     *
     * @return T
     */
    private function run($url, $download)
    {
        for ($attempt = 1;; $attempt++) {
            try {
                return $download();
            } catch (Exception\IO $x) {
                // Retrying doesn't change these answers of the server
                if ($attempt > $this->retries || $x instanceof Exception\IO\NotFound || $x instanceof Exception\IO\Forbidden) {
                    throw $x;
                }
                $this->logger->warning("Download of {$url} failed (retry {$attempt} of {$this->retries} in {$this->delay} seconds):\n{$x->getMessage()}");
                if ($this->delay > 0) {
                    sleep($this->delay);
                }
            }
        }
    }
}
