<?php

namespace IPE\Filesystem\Downloader;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;

/**
 * The wget of BusyBox (for example, in Alpine Linux): it has fewer options than GNU wget.
 */
final class WithBusyBoxWgetCommand extends WithCommand
{
    /**
     * @param array{proxy?: string, insecure?: bool} $config
     *
     * @throws \IPE\Exception\IO
     *
     * @return static|null
     */
    public static function createIfAvailable(array $config, Filesystem $fs, \IPE\Logger $logger)
    {
        $command = $fs->resolveCommand('wget');
        if ($command !== '') {
            // BusyBox doesn't support --version, but it prints its name with the help
            list(, $output) = $fs->run(Command::create($command, ['--help']));
            if (preg_match('/^BusyBox v\d/m', $output)) {
                return new self($command, $config, $fs, $logger);
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader\WithCommand::buildDownloadEnvironment()
     */
    protected function buildDownloadEnvironment()
    {
        if (!$this->hasProxy()) {
            return [];
        }
        $proxyUrl = $this->getProxyUrl(true);

        return [
            'http_proxy' => $proxyUrl,
            'https_proxy' => $proxyUrl,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader\WithCommand::buildDownloadArguments()
     */
    protected function buildDownloadArguments($url, $filename)
    {
        $args = [
            // Quiet
            '-q',
            // User-Agent header
            '-U', $this->buildUserAgent('BusyBoxWgetCommand'),
            // Use the proxy (configured in buildDownloadEnvironment())
            '-Y', $this->hasProxy() ? 'on' : 'off',
            // Save to file
            '-O', $filename,
        ];
        if ($this->isInsecure()) {
            // Don't validate the server certificate
            $args[] = '--no-check-certificate';
        }
        $args[] = $url;

        return $args;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader\WithCommand::getErrorResponseClass()
     */
    protected function getErrorResponseClass($rc, $output)
    {
        if (preg_match('/server returned error: HTTP\/[\d.]+ 404\b/i', $output)) {
            return Exception\IO\NotFound::class;
        }
        if (preg_match('/server returned error: HTTP\/[\d.]+ 403\b/i', $output)) {
            return Exception\IO\Forbidden::class;
        }

        return parent::getErrorResponseClass($rc, $output);
    }
}
