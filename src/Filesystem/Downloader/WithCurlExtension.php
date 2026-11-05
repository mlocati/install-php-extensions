<?php

namespace IPE\Filesystem\Downloader;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Service\Url;
use IPE\Traits\WarningCatcher;

final class WithCurlExtension extends AbstractDownloader
{
    use WarningCatcher;

    /**
     * @var array<int, mixed> the cURL options, keyed by the CURLOPT_* constants
     */
    private $options;

    protected function __construct($config, Filesystem $fs, \IPE\Logger $logger)
    {
        parent::__construct($config, $fs, $logger);
        $this->options = $this->createOptions();
    }

    /**
     * @param array{proxy?: string, insecure?: bool} $config
     *
     * @return static|null
     */
    public static function createIfAvailable(array $config, Filesystem $fs, \IPE\Logger $logger)
    {
        if (!extension_loaded('curl')) {
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
        return $this->fetch($url);
    }

    /**
     * @return array<int, mixed> the cURL options, keyed by the CURLOPT_* constants
     */
    private function createOptions()
    {
        $options = [
            CURLOPT_FAILONERROR => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => $this->buildUserAgent('CurlExtension'),
        ];
        if ($this->hasProxy()) {
            $options[CURLOPT_PROXY] = $this->getProxyHostname();
            $options[CURLOPT_PROXYPORT] = $this->getProxyPort();
            if ($this->hasProxyAuthentication()) {
                $options[CURLOPT_PROXYUSERPWD] = $this->getProxyUserPassword();
            }
        }
        if ($this->isInsecure()) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        return $options;
    }

    /**
     * @param non-empty-string $url
     *
     * @throws \IPE\Exception\IO
     *
     * @return string
     */
    private function fetch($url)
    {
        $url = Url::validate($url);
        $this->logger->debug('Downloading with cURL extension', ['url' => $url]);
        list($ch, $errorDetails) = self::wrap(static function () use ($url) {
            return curl_init($url);
        }, 'curl_init');
        if ($ch === false) {
            throw new Exception\IO(self::buildErrorMessage('curl_init() failed', $errorDetails));
        }
        try {
            $describeCurlError = static function () use ($ch) {
                $error = trim(curl_error($ch));

                return $error !== '' ? $error : ('cURL error #' . curl_errno($ch));
            };
            $options = [CURLOPT_RETURNTRANSFER => true] + $this->options;
            list($success, $errorDetails) = self::wrap(static function () use ($ch, $options) {
                return curl_setopt_array($ch, $options);
            }, 'curl_setopt_array()');
            if ($success === false) {
                throw new Exception\IO(self::buildErrorMessage($describeCurlError(), $errorDetails));
            }
            list($result, $errorDetails) = self::wrap(static function () use ($ch) {
                return curl_exec($ch);
            }, 'curl_exec()');
            if ($result === false) {
                $curlError = $describeCurlError();
                $message = self::buildErrorMessage($curlError, $errorDetails);
                // 22: The requested URL was not found or returned another error with the HTTP error code being 400 or above
                // See https://everything.curl.dev/cmdline/exitcode.html
                if (curl_errno($ch) === 22 && preg_match('/\b404\b/', $curlError)) {
                    throw new Exception\IO\NotFound($message);
                }
                if (curl_errno($ch) === 22 && preg_match('/\b403\b/', $curlError)) {
                    throw new Exception\IO\Forbidden($message);
                }
                throw new Exception\IO($message);
            }
            if (!is_string($result)) {
                throw new Exception\IO("Unexpected result from cURL while fetching {$url}");
            }
        } finally {
            if (PHP_VERSION_ID < 80000) {
                self::wrap(static function () use ($ch) { curl_close($ch); });
            }
        }

        return $result;
    }
}
