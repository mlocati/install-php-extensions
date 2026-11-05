<?php

namespace IPE\Filesystem\ArchiveExtractor\FormatExtractor;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor\FormatExtractor;
use IPE\Filesystem\Command;

/**
 * Extracts zip archives (GNU tar can't do that).
 */
final class WithBsdtarCommand extends FormatExtractor
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
     * @return \IPE\Filesystem\ArchiveExtractor\FormatExtractor\WithBsdtarCommand|null
     */
    public static function createIfAvailable(Filesystem $fs, \IPE\Logger $logger)
    {
        $command = $fs->resolveCommand('bsdtar');
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
        $this->logger->debug('Extracting archive with bsdtar command', ['archive' => $archive, 'destinationDirectory' => $destinationDirectory]);
        list($rc, $output) = $this->fs->run(Command::create($this->command, ['-x', "-f{$archive}", "-C{$destinationDirectory}"]));
        if ($rc !== 0) {
            throw new Exception\IO("Failed to extract file '{$archive}' to '{$destinationDirectory}': {$output}");
        }
    }
}
