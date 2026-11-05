<?php

namespace IPE\Test\Support\DownloaderTest;

use IPE\Filesystem;
use IPE\Filesystem\Downloader\AbstractDownloader;

final class ProxyDownloader extends AbstractDownloader
{
    /**
     * @param array{proxy?: string} $config
     */
    public function __construct(array $config, Filesystem $fs)
    {
        parent::__construct($config, $fs, new \IPE\Logger());
    }

    /**
     * @return string
     */
    public function getPublicProxyUrl()
    {
        return $this->getProxyUrl();
    }
}
