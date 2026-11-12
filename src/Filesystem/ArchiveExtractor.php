<?php

namespace IPE\Filesystem;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor\FormatExtractor;

class ArchiveExtractor
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var \IPE\Filesystem
     * @readonly
     */
    protected $fs;

    public function __construct(Filesystem $fs, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->fs = $fs;
    }

    /**
     * @return \IPE\Filesystem
     */
    public function getFilesystem()
    {
        return $this->fs;
    }

    /**
     * @param non-empty-string $format
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    public function canHandleFormat($format)
    {
        return $this->getExtractorForFormat($format) !== null;
    }

    /**
     * @param non-empty-string $archive
     * @param non-empty-string $destinationDirectory
     * @param string $format one of the ArchiveFormat constants (empty: detect from the file name)
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    public function extractTo($archive, $destinationDirectory, $format = '')
    {
        if ($format !== '') {
            $extractor = $this->getExtractorForFormat($format);
            if ($extractor === null) {
                throw new Exception\UnsupportedArchiveFormat("Unsupported archive format: {$format}");
            }

            return $extractor->extractTo($archive, $destinationDirectory);
        }
        $formats = $this->getPossibleFormatsForFile($archive);
        if ($formats === []) {
            throw new Exception\UnsupportedArchiveFormat("Unable to find any extractor for file {$archive}");
        }
        $firstExcception = null;
        foreach ($formats as $format) {
            $extractor = $this->getExtractorForFormat($format);
            if ($extractor === null) {
                continue;
            }
            try {
                return $extractor->extractTo($archive, $destinationDirectory);
            } catch (Exception\IO $x) {
                if ($firstExcception === null) {
                    $firstExcception = $x;
                }
            }
        }
        if ($firstExcception !== null) {
            throw $firstExcception;
        }
        throw new Exception\UnsupportedArchiveFormat('Unsupported archive format');
    }

    /**
     * Not cached: the installation and the removal of the system packages change the available commands.
     *
     * @param non-empty-string $format
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Filesystem\ArchiveExtractor\FormatExtractor|null
     */
    protected function getExtractorForFormat($format)
    {
        if ($format === ArchiveFormat::ZIP) {
            return FormatExtractor\WithBsdtarCommand::createIfAvailable($this->fs, $this->logger)
                ?: FormatExtractor\WithUnzipCommand::createIfAvailable($this->fs, $this->logger)
                ?: FormatExtractor\WithZipExtension::createIfAvailable($this->fs, $this->logger)
                ?: FormatExtractor\WithPharExtension::createIfAvailable($format, $this->fs, $this->logger)
                ?: FormatExtractor\WithPowershellCommand::createIfAvailable($this->fs, $this->logger);
        }
        if ($format === ArchiveFormat::TAR) {
            return
                FormatExtractor\WithTarCommand::createIfAvailable($format, $this->fs, $this->logger)
                ?: FormatExtractor\WithPharExtension::createIfAvailable($format, $this->fs, $this->logger)
                ?: null;
        }
        if (in_array($format, [
            ArchiveFormat::TAR_GZ,
            ArchiveFormat::TAR_BZ,
            ArchiveFormat::TAR_XZ,
        ], true)) {
            $extractor = FormatExtractor\WithTarCommand::createIfAvailable($format, $this->fs, $this->logger);
            if ($extractor !== null) {
                return $extractor;
            }
            $tarExtractor = $this->getExtractorForFormat(ArchiveFormat::TAR);

            return $tarExtractor === null ? null : FormatExtractor\WithStreamWrapper::createIfAvailable($format, $this->fs, $this->logger, $tarExtractor);
        }

        throw new Exception\InvalidArgument('Invalid argument $format');
    }

    /**
     * @param non-empty-string $archive
     *
     * @throws \IPE\Exception\IO
     *
     * @return list<non-empty-string>
     */
    protected function getPossibleFormatsForFile($archive)
    {
        $archive = $this->fs->getRealPath($archive);
        if (!$this->fs->isFile($archive)) {
            throw new Exception\IO("Unable to find the file '{$archive}'");
        }
        if (!$this->fs->isReadable($archive)) {
            throw new Exception\IO("The file '{$archive}' is not readable");
        }
        $firstBytes = $this->fs->readFile($archive, 280);
        $first2Bytes = substr($firstBytes, 0, 2);
        $possibleFormats = [];
        if ($first2Bytes === 'PK') {
            $possibleFormats[] = ArchiveFormat::ZIP;
        } elseif ($first2Bytes === "\x1F\x8B") {
            $possibleFormats[] = ArchiveFormat::TAR_GZ;
        } elseif ($first2Bytes === 'BZ') {
            $possibleFormats[] = ArchiveFormat::TAR_BZ;
        } elseif (substr($firstBytes, 0, 6) === "\xFD" . '7zXZ' . "\x00") {
            $possibleFormats[] = ArchiveFormat::TAR_XZ;
        }
        $subBytes = substr($firstBytes, 257, 6);
        if ($subBytes === 'ustar ' || $subBytes === "ustar\x00") {
            $possibleFormats[] = ArchiveFormat::TAR;
        }
        $this->logger->debug('Possible archive formats detected', ['formats' => $possibleFormats]);

        return $possibleFormats;
    }
}
