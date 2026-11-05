<?php

namespace IPE\Filesystem\Downloader;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;

final class WithWgetCommand extends WithCommand
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
            list($rc, $output) = $fs->run(Command::create($command, ['--version']));
            if ($rc === 0 && preg_match('/^(\w+ )?wget \d+\.\d/im', $output)) {
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
        $args = [];
        if ($this->hasProxy()) {
            $proxyUrl = $this->getProxyUrl(true);
            $args[] = '-e';
            $args[] = 'use_proxy=yes';
            $args[] = '-e';
            $args[] = "https_proxy={$proxyUrl}";
            $args[] = '-e';
            $args[] = "http_proxy={$proxyUrl}";
        }
        if ($this->isInsecure()) {
            $args[] = '--no-check-certificate';
        }

        return array_merge($args, [
            '-U', $this->buildUserAgent('WgetCommand'),
            '-nv',
            '-O', $filename,
            $url,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader\WithCommand::getErrorResponseClass()
     */
    protected function getErrorResponseClass($rc, $output)
    {
        // 8: Server issued an error response
        // See https://www.gnu.org/software/wget/manual/html_node/Exit-Status.html
        if ($rc === 8 && preg_match('/ERROR 404\b/i', $output)) {
            return Exception\IO\NotFound::class;
        }
        if ($rc === 8 && preg_match('/ERROR 403\b/i', $output)) {
            return Exception\IO\Forbidden::class;
        }

        return parent::getErrorResponseClass($rc, $output);
    }
}
