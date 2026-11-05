<?php

namespace IPE\Filesystem\ArchiveExtractor\FormatExtractor;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor\FormatExtractor;
use IPE\Filesystem\ArchiveFormat;
use IPE\Filesystem\Command;

final class WithTarCommand extends FormatExtractor
{
    /**
     * @var non-empty-array<non-empty-string, non-empty-string>
     */
    private $commandsAndOptions;

    /**
     * @param non-empty-array<non-empty-string, non-empty-string> $commandsAndOptions
     */
    protected function __construct(Filesystem $fs, \IPE\Logger $logger, array $commandsAndOptions)
    {
        parent::__construct($fs, $logger);
        $this->commandsAndOptions = $commandsAndOptions;
    }

    /**
     * @param non-empty-string $format
     *
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Filesystem\ArchiveExtractor\FormatExtractor\WithTarCommand|null
     */
    public static function createIfAvailable($format, Filesystem $fs, \IPE\Logger $logger)
    {
        $commandsAndOptions = [];
        foreach (['tar', 'bsdtar'] as $wantedCommand) {
            $command = $fs->resolveCommand($wantedCommand);
            if ($command === '') {
                continue;
            }
            if ($format === ArchiveFormat::TAR) {
                $commandsAndOptions[$command] = '-x';
                continue;
            }
            list($rc, $output) = $fs->run(Command::create($command, ['--help']));
            if ($rc !== 0) {
                continue;
            }
            if ($format === ArchiveFormat::TAR_BZ) {
                if (preg_match('#(^|\s|,)-j($|\s|,)#', $output)) {
                    if ($fs->getDirectorySeparator() === '\\' || $fs->resolveCommand('bzip2') !== '') {
                        $commandsAndOptions[$command] = '-xj';
                    }
                }
            } elseif ($format === ArchiveFormat::TAR_GZ) {
                if (preg_match('#(^|\s|,)-z($|\s|,)#', $output)) {
                    if ($fs->getDirectorySeparator() === '\\' || $fs->resolveCommand('gzip') !== '') {
                        $commandsAndOptions[$command] = '-xz';
                    }
                }
            } elseif ($format === ArchiveFormat::TAR_XZ) {
                if (preg_match('#(^|\s|,)-J($|\s|,)#', $output)) {
                    if ($fs->getDirectorySeparator() === '\\' || $fs->resolveCommand('xz') !== '') {
                        $commandsAndOptions[$command] = '-xJ';
                    }
                }
            }
        }
        if ($commandsAndOptions === []) {
            return null;
        }

        return new self($fs, $logger, $commandsAndOptions);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\ArchiveExtractor\FormatExtractor::doExtractTo()
     */
    protected function doExtractTo($archive, $destinationDirectory)
    {
        $firstException = null;
        foreach ($this->commandsAndOptions as $command => $option) {
            try {
                $this->doExtractToWithCommand($archive, $destinationDirectory, $command, $option);

                return;
            } catch (Exception\IO $x) {
                if ($firstException === null) {
                    $firstException = $x;
                }
            }
        }

        throw $firstException;
    }

    /**
     * @param non-empty-string $archive the real path to an existing and readable file
     * @param non-empty-string $destinationDirectory the real path to an existing and writable directory
     * @param non-empty-string $command
     * @param non-empty-string $option
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function doExtractToWithCommand($archive, $destinationDirectory, $command, $option)
    {
        $arguments = [
            $option,
            "-f{$archive}",
            "-C{$destinationDirectory}",
        ];
        $this->logger->debug('Extracting archive with tar command', ['archive' => $archive, 'destinationDirectory' => $destinationDirectory]);
        list($rc, $output) = $this->fs->run(Command::create($command, $arguments));
        if ($rc !== 0) {
            throw new Exception\IO("Failed to extract file '{$archive}' to '{$destinationDirectory}': {$output}");
        }
    }
}
