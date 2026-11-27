<?php

namespace IPE\Filesystem\Downloader;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Service\Regex;
use IPE\Service\Url;
use IPE\Traits\WarningCatcher;

final class WithStreamWrapper extends AbstractDownloader
{
    use WarningCatcher;

    /**
     * @var resource
     */
    private $context;

    /**
     * @param array{proxy?: string, insecure?: bool} $config
     */
    protected function __construct(array $config, Filesystem $fs, \IPE\Logger $logger)
    {
        parent::__construct($config, $fs, $logger);
        $this->context = $this->createContext();
    }

    /**
     * @param array{proxy?: string, insecure?: bool} $config
     *
     * @return static|null
     */
    public static function createIfAvailable(array $config, Filesystem $fs, \IPE\Logger $logger)
    {
        $streamWrappers = array_map('strtolower', stream_get_wrappers());
        // Without the openssl extension there's only http, but most of the URLs are https
        if (!in_array('http', $streamWrappers, true) || !in_array('https', $streamWrappers, true)) {
            return null;
        }

        return new self($config, $fs, $logger);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::getContent()
     * @see \IPE\Filesystem\Downloader\AbstractDownloader::getContent()
     */
    public function getContent($url)
    {
        $url = Url::validate($url);
        $this->logger->debug('Downloading with file_get_contents()', ['url' => $url]);
        if (function_exists('http_clear_last_response_headers')) {
            http_clear_last_response_headers();
        }
        list($content, $errorDetails) = self::wrap(function () use ($url) {
            return file_get_contents($url, false, $this->context);
        }, 'file_get_contents');
        if ($content === false) {
            throw self::createFetchException($url, $errorDetails);
        }

        return $content;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::saveAs()
     * @see \IPE\Filesystem\Downloader\AbstractDownloader::saveAs()
     */
    public function saveAs($url, $filename)
    {
        $url = Url::validate($url);
        $this->logger->debug('Downloading with fopen()', ['url' => $url, 'filename' => $filename]);
        if (function_exists('http_clear_last_response_headers')) {
            http_clear_last_response_headers();
        }
        list($stream, $errorDetails) = self::wrap(function () use ($url) {
            return fopen($url, 'rb', false, $this->context);
        }, 'fopen');
        if ($stream === false) {
            throw self::createFetchException($url, $errorDetails);
        }
        try {
            // Unlike getContent(), it doesn't load the whole file in memory (some archives exceed the default memory_limit)
            $this->fs->writeFileFromStream($filename, $stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param non-empty-string $url
     * @param string $errorDetails
     *
     * @return \IPE\Exception\IO
     */
    private static function createFetchException($url, $errorDetails)
    {
        $statusLine = '';
        if (function_exists('http_get_last_response_headers')) {
            $headers = http_get_last_response_headers();
            if (isset($headers[0])) {
                $statusLine = $headers[0];
            }
        }
        $message = self::buildErrorMessage("Failed to fetch {$url}", $errorDetails);
        if (strpos($errorDetails, '404 Not Found') !== false || preg_match('/\b404\b/', $statusLine)) {
            return new Exception\IO\NotFound($message);
        }
        if (strpos($errorDetails, '403 Forbidden') !== false || preg_match('/\b403\b/', $statusLine)) {
            return new Exception\IO\Forbidden($message);
        }

        return new Exception\IO($message);
    }

    /**
     * @return resource
     */
    private function createContext()
    {
        $options = [
            'method' => 'GET',
            'user_agent' => $this->buildUserAgent('StreamWrapper'),
        ];
        if ($this->hasProxy()) {
            $options['request_fulluri'] = true;
            $options['proxy'] = $this->getProxyUrl();
            $options['proxy'] = Regex::replace('#^http:#i', 'tcp:', $options['proxy']);
            $options['proxy'] = Regex::replace('#^https:#i', 'ssl:', $options['proxy']);
            if ($this->hasProxyAuthentication()) {
                $options['header'] = 'Proxy-Authorization: Basic ' . base64_encode($this->getProxyUserPassword()) . "\r\n";
            }
        }

        $contextOptions = ['http' => $options];
        if ($this->isInsecure()) {
            $contextOptions['ssl'] = [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ];
        }

        return stream_context_create($contextOptions);
    }
}
