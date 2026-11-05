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
        if (!in_array('http', $streamWrappers, true)) {
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
            if (function_exists('http_get_last_response_headers')) {
                $http_response_header = http_get_last_response_headers();
            }
            $is404 = strpos($errorDetails, '404 Not Found') !== false;
            if (!$is404) {
                $is404 = isset($http_response_header[0]) && $http_response_header[0] !== '' && preg_match('/\b404\b/', $http_response_header[0]);
            }
            if ($is404) {
                throw new Exception\IO\NotFound(self::buildErrorMessage("Failed to fetch {$url}", $errorDetails));
            }
            $is403 = strpos($errorDetails, '403 Forbidden') !== false;
            if (!$is403) {
                $is403 = isset($http_response_header[0]) && $http_response_header[0] !== '' && preg_match('/\b403\b/', $http_response_header[0]);
            }
            if ($is403) {
                throw new Exception\IO\Forbidden(self::buildErrorMessage("Failed to fetch {$url}", $errorDetails));
            }

            throw new Exception\IO(self::buildErrorMessage("Failed to fetch {$url}", $errorDetails));
        }

        return $content;
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
