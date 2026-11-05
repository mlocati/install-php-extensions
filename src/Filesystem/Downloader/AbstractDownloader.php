<?php

namespace IPE\Filesystem\Downloader;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Downloader;

/**
 * Child classes must implement saveAs() and/or getContent().
 */
abstract class AbstractDownloader implements Downloader
{
    /**
     * @var \IPE\Logger
     */
    protected $logger;

    /**
     * @var array{protocol: string, hostname: string, port: int, user: string, password: string}|null
     */
    private $proxyConfig;

    /**
     * @var bool
     */
    private $insecure;

    /**
     * @var \IPE\Filesystem
     */
    protected $fs;

    /**
     * @param array{proxy?: string, insecure?: bool} $config
     */
    protected function __construct(array $config, Filesystem $fs, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->proxyConfig = self::extractProxyConfig($config);
        $this->insecure = isset($config['insecure']) && $config['insecure'];
        $this->fs = $fs;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::saveAs()
     */
    public function saveAs($url, $filename)
    {
        $content = $this->getContent($url);
        $this->fs->writeFile($filename, $content);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::getContent()
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\IO\Forbidden
     * @throws \IPE\Exception\IO\NotFound
     */
    public function getContent($url)
    {
        $tempDir = $this->fs->createVolatileDirectory();
        $tempFile = $tempDir->getPath() . '/downloaded';
        $this->saveAs($url, $tempFile);

        return $this->fs->readFile($tempFile);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::getFilesystem()
     */
    public function getFilesystem()
    {
        return $this->fs;
    }

    /**
     * Should the TLS certificates of the servers not be verified?
     *
     * @return bool
     */
    protected function isInsecure()
    {
        return $this->insecure;
    }

    /**
     * @return bool
     */
    protected function hasProxy()
    {
        return $this->proxyConfig !== null;
    }

    /**
     * @return bool
     */
    protected function hasProxyAuthentication()
    {
        return $this->proxyConfig !== null && $this->proxyConfig['user'] !== '';
    }

    /**
     * Get the proxy URL as protocol://hostname:port.
     *
     * @param bool $withAuthentication if set to true: return protocol://user:password@hostname:port
     *
     * @return string
     */
    protected function getProxyUrl($withAuthentication = false)
    {
        if ($this->proxyConfig === null) {
            return '';
        }
        $result = "{$this->proxyConfig['protocol']}://";
        if ($withAuthentication) {
            $auth = $this->getProxyUserPassword();
            if ($auth !== '') {
                $result .= "{$auth}@";
            }
        }

        return $result . "{$this->proxyConfig['hostname']}:{$this->proxyConfig['port']}";
    }

    /**
     * @return string
     */
    protected function getProxyHostname()
    {
        return $this->proxyConfig === null ? '' : $this->proxyConfig['hostname'];
    }

    /**
     * @return int|null
     */
    protected function getProxyPort()
    {
        return $this->proxyConfig === null ? null : $this->proxyConfig['port'];
    }

    /**
     * Get the proxy user and password as user:password.
     *
     * @return string
     */
    protected function getProxyUserPassword()
    {
        if ($this->proxyConfig === null || $this->proxyConfig['user'] === '') {
            return '';
        }

        return "{$this->proxyConfig['user']}:{$this->proxyConfig['password']}";
    }

    /**
     * @param array{proxy?: string, insecure?: bool} $config
     *
     * @return array{protocol: string, hostname: string, port: int, user: string, password: string}|null
     */
    private function extractProxyConfig(array $config)
    {
        $proxy = array_key_exists('proxy', $config) ? $config['proxy'] : '';
        if ($proxy === '') {
            return null;
        }
        $parts = parse_url($proxy);
        if ($parts === false) {
            throw new Exception\InvalidArgument('Invalid argument $config[\'proxy\']');
        }
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        if ($scheme === '') {
            throw new Exception\InvalidArgument('Invalid argument $config[\'proxy\']: missing scheme');
        }
        $host = isset($parts['host']) ? $parts['host'] : '';
        if ($host === '') {
            throw new Exception\InvalidArgument('Invalid argument $config[\'proxy\']: missing host');
        }
        if (isset($parts['port'])) {
            $port = filter_var($parts['port'], FILTER_VALIDATE_INT, [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 65535,
                ],
            ]);
            if ($port === false) {
                throw new Exception\InvalidArgument('Invalid argument $config[\'proxy\']: invalid port');
            }
        } elseif (in_array($scheme, ['http', 'tcp'], true)) {
            $port = 80;
        } elseif (in_array($scheme, ['https', 'ssl'], true)) {
            $port = 443;
        } else {
            throw new Exception\InvalidArgument('Incomplete proxy URL provided in $config[\'proxy\']: missing port');
        }
        $user = isset($parts['user']) ? $parts['user'] : '';
        $pass = isset($parts['pass']) ? $parts['pass'] : '';
        if ($user === '' && $pass !== '') {
            throw new Exception\InvalidArgument('A proxy password was provided without a proxy user');
        }
        if ($user !== '' && $pass === '') {
            throw new Exception\InvalidArgument('A proxy user was provided without a proxy password');
        }

        return [
            'protocol' => $scheme,
            'hostname' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $pass,
        ];
    }

    /**
     * @param non-empty-string $component
     *
     * @return non-empty-string
     */
    protected function buildUserAgent($component)
    {
        $result = "IPE-{$component}";
        if (defined('IPE_VERSION') && IPE_VERSION !== '') {
            $result .= '/' . IPE_VERSION;
        }

        return $result . ' (+https://github.com/mlocati/install-php-extensions)';
    }
}
