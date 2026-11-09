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
        $command = Command::create(
            $this->command,
            $this->buildDownloadArguments($url, str_replace('/', $this->fs->getDirectorySeparator(), $filename))
        );
        $environment = $this->buildDownloadEnvironment();
        if ($environment !== []) {
            $command = $command->withEnvironment($environment);
        }
        list($rc, $output) = $this->fs->run($command);
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
     * @return array<non-empty-string, string> the environment variables to be added when running the command
     */
    protected function buildDownloadEnvironment()
    {
        return [];
    }

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
