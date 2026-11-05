<?php

namespace IPE\Filesystem\ArchiveExtractor\FormatExtractor;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor\FormatExtractor;
use IPE\Filesystem\Command;

final class WithUnzipCommand extends FormatExtractor
{
    /**
     * @var non-empty-string
     */
    private $command;

    /**
     * @param non-empty-string $command
     */
    protected function __construct(Filesystem $fs, \IPE\Logger $logger, $command)
    {
        parent::__construct($fs, $logger);
        $this->command = $command;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Filesystem\ArchiveExtractor\FormatExtractor\WithUnzipCommand|null
     */
    public static function createIfAvailable(Filesystem $fs, \IPE\Logger $logger)
    {
        $command = $fs->resolveCommand('unzip');
        if ($command === '') {
            return null;
        }

        return new self($fs, $logger, $command);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\ArchiveExtractor\FormatExtractor::doExtractTo()
     */
    protected function doExtractTo($archive, $destinationDirectory)
    {
        $arguments = [
            // overwrite existing files (like tar)
            '-o',
            // quiet mode
            '-q',
            // extract files into dir
            '-d', $destinationDirectory,
            $archive,
        ];
        $this->logger->debug('Extracting archive with zip command', ['archive' => $archive, 'destinationDirectory' => $destinationDirectory]);
        list($rc, $output) = $this->fs->run(Command::create($this->command, $arguments));
        if ($rc !== 0) {
            throw new Exception\IO("Failed to extract file '{$archive}' to '{$destinationDirectory}': {$output}");
        }
    }
}
