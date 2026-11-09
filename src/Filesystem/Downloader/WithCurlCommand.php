<?php

namespace IPE\Filesystem\Downloader;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;

final class WithCurlCommand extends WithCommand
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
        $command = $fs->resolveCommand('curl');
        if ($command !== '') {
            list($rc, $output) = $fs->run(Command::create($command, ['--version']));
            if ($rc === 0 && preg_match('/^curl \d+\.\d/', $output)) {
                return new self($command, $config, $fs, $logger);
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader\WithCommand::buildDownloadArguments()
     */
    protected function buildDownloadArguments($url, $filename)
    {
        $args = [
            // Don't show the progress meter and the error messages...
            '-s',
            // ...but show the error messages
            '-S',
            // Follow the redirects
            '-L',
            // Fail on HTTP errors
            '-f',
            // User-Agent header
            '-A', $this->buildUserAgent('CurlCommand'),
            // Save to file
            '--output', $filename,
        ];
        if ($this->hasProxy()) {
            // Use the proxy
            $args[] = '--proxy';
            $args[] = $this->getProxyUrl();
            if ($this->hasProxyAuthentication()) {
                // Proxy credentials
                $args[] = '--proxy-user';
                $args[] = $this->getProxyUserPassword();
            }
        }
        if ($this->isInsecure()) {
            // Don't validate the server certificate
            $args[] = '--insecure';
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
        // 22: The requested URL was not found or returned another error with the HTTP error code being 400 or above
        // See https://everything.curl.dev/cmdline/exitcode.html
        if ($rc === 22 && preg_match('/\b404\b/', $output)) {
            return Exception\IO\NotFound::class;
        }
        if ($rc === 22 && preg_match('/\b403\b/', $output)) {
            return Exception\IO\Forbidden::class;
        }

        return parent::getErrorResponseClass($rc, $output);
    }
}
