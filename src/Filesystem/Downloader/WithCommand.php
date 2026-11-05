<?php

namespace IPE\Filesystem\Downloader;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Service\Url;

abstract class WithCommand extends AbstractDownloader
{
    /**
     * @var non-empty-string
     */
    protected $command;

    /**
     * @param non-empty-string $command
     * @param array{proxy?: string, insecure?: bool} $config
     */
    protected function __construct($command, array $config, Filesystem $fs, \IPE\Logger $logger)
    {
        parent::__construct($config, $fs, $logger);
        $this->command = $command;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::saveAs()
     */
    public function saveAs($url, $filename)
    {
        $url = Url::validate($url);
        $this->logger->debug('Downloading with command', ['command' => $this->command, 'url' => $url]);
        list($rc, $output) = $this->fs->run(Command::create(
            $this->command,
            $this->buildDownloadArguments($url, str_replace('/', $this->fs->getDirectorySeparator(), $filename))
        ));
        if ($rc !== 0) {
            $class = $this->getErrorResponseClass($rc, $output);
            throw new $class("Failed to download {$url} with {$this->command}:\n{$output}");
        }
    }

    /**
     * @param string $url
     * @param string $filename
     *
     * @return list<string>
     */
    abstract protected function buildDownloadArguments($url, $filename);

    /**
     * Get the class of the exception to be thrown when the command fails.
     *
     * @param int $rc
     * @param string $output
     *
     * @return class-string<\IPE\Exception\IO>
     */
    protected function getErrorResponseClass($rc, $output)
    {
        return Exception\IO::class;
    }
}
