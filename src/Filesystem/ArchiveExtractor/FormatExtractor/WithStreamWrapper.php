<?php

namespace IPE\Filesystem\ArchiveExtractor\FormatExtractor;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor\FormatExtractor;
use IPE\Filesystem\ArchiveFormat;
use IPE\Traits;

/**
 * Decompress tar.gz and tar.bz archives with the compress.zlib:// and compress.bzip2:// stream wrappers, and extract the resulting tar archive with another extractor.
 */
final class WithStreamWrapper extends FormatExtractor
{
    use Traits\WarningCatcher;

    /**
     * @var \IPE\Filesystem\ArchiveFormat::TAR_BZ|\IPE\Filesystem\ArchiveFormat::TAR_GZ
     */
    private $format;

    /**
     * @var \IPE\Filesystem\ArchiveExtractor\FormatExtractor
     */
    private $tarExtractor;

    /**
     * @param \IPE\Filesystem\ArchiveFormat::TAR_BZ|\IPE\Filesystem\ArchiveFormat::TAR_GZ $format
     */
    protected function __construct(Filesystem $fs, \IPE\Logger $logger, $format, FormatExtractor $tarExtractor)
    {
        parent::__construct($fs, $logger);
        $this->format = $format;
        $this->tarExtractor = $tarExtractor;
    }

    /**
     * @param non-empty-string $format
     * @param \IPE\Filesystem\ArchiveExtractor\FormatExtractor $tarExtractor the extractor of uncompressed tar archives
     *
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Filesystem\ArchiveExtractor\FormatExtractor\WithStreamWrapper|null
     */
    public static function createIfAvailable($format, Filesystem $fs, \IPE\Logger $logger, FormatExtractor $tarExtractor)
    {
        // The stream wrappers can only read local files
        if (!$fs->isLocal()) {
            return null;
        }
        if ($format === ArchiveFormat::TAR_GZ) {
            return in_array('compress.zlib', stream_get_wrappers(), true) ? new self($fs, $logger, $format, $tarExtractor) : null;
        }
        if ($format === ArchiveFormat::TAR_BZ) {
            return in_array('compress.bzip2', stream_get_wrappers(), true) ? new self($fs, $logger, $format, $tarExtractor) : null;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\ArchiveExtractor\FormatExtractor::doExtractTo()
     */
    protected function doExtractTo($archive, $destinationDirectory)
    {
        $tmp = $this->fs->createVolatileDirectory();
        $tarFile = $tmp->getPath() . $this->fs->getDirectorySeparator() . 'archive.tar';
        $this->logger->debug('Decompressing archive with stream wrapper', ['archive' => $archive, 'tarFile' => $tarFile]);
        $size = $this->decompress($archive, $tarFile);
        if ($this->format === ArchiveFormat::TAR_GZ) {
            $this->checkGzipTrailer($archive, $tarFile, $size);
        }
        $this->tarExtractor->extractTo($tarFile, $destinationDirectory);
    }

    /**
     * @param non-empty-string $archive
     * @param non-empty-string $tarFile
     *
     * @throws \IPE\Exception\IO
     *
     * @return int the size of the decompressed data
     */
    private function decompress($archive, $tarFile)
    {
        $url = ($this->format === ArchiveFormat::TAR_GZ ? 'compress.zlib' : 'compress.bzip2') . '://' . $archive;
        list($source, $errorDetails) = self::wrap(
            static function () use ($url) {
                return fopen($url, 'rb');
            },
            'fopen'
        );
        if ($source === false) {
            throw new Exception\IO(self::buildErrorMessage("Failed to open the archive '{$archive}'", $errorDetails));
        }
        try {
            list($destination, $errorDetails) = self::wrap(
                static function () use ($tarFile) {
                    return fopen($tarFile, 'wb');
                },
                'fopen'
            );
            if ($destination === false) {
                throw new Exception\IO(self::buildErrorMessage("Failed to create the file '{$tarFile}'", $errorDetails));
            }
            try {
                // Before PHP 7.4 this doesn't fail with damaged archives
                list($copied, $errorDetails) = self::wrap(
                    static function () use ($source, $destination) {
                        return stream_copy_to_stream($source, $destination);
                    },
                    'stream_copy_to_stream'
                );
            } finally {
                fclose($destination);
            }
        } finally {
            fclose($source);
        }
        if ($copied === false) {
            throw new Exception\IO(self::buildErrorMessage("Failed to decompress the archive '{$archive}'", $errorDetails));
        }

        return $copied;
    }

    /**
     * The zlib stream wrapper doesn't detect truncated archives: compare the trailer of the gzip file (the CRC32 and the size modulo 2^32 of the uncompressed data) with the decompressed data.
     * In files with more gzip members, the trailer describes only the last one: we don't support them.
     *
     * @param non-empty-string $archive
     * @param non-empty-string $tarFile
     * @param int $size the size of the decompressed data
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function checkGzipTrailer($archive, $tarFile, $size)
    {
        list($trailer, $errorDetails) = self::wrap(
            static function () use ($archive) {
                $handle = fopen($archive, 'rb');
                if ($handle === false) {
                    return false;
                }
                try {
                    return fseek($handle, -8, SEEK_END) === 0 ? fread($handle, 8) : false;
                } finally {
                    fclose($handle);
                }
            }
        );
        if (!is_string($trailer) || strlen($trailer) !== 8) {
            throw new Exception\IO(self::buildErrorMessage("Failed to read the end of the archive '{$archive}'", $errorDetails));
        }
        // Compared as bytes: unpack() returns negative numbers on 32-bit PHP, and pack() keeps the lowest 32 bits
        if (substr($trailer, 4) !== pack('V', $size)) {
            throw new Exception\IO("The archive '{$archive}' is truncated or damaged");
        }
        // ext/hash can be disabled before PHP 7.4, and hash_file() can be listed in disable_functions
        if (!function_exists('hash_file')) {
            return;
        }
        list($crc, $errorDetails) = self::wrap(
            static function () use ($tarFile) {
                return hash_file('crc32b', $tarFile);
            },
            'hash_file'
        );
        if (!is_string($crc)) {
            throw new Exception\IO(self::buildErrorMessage("Failed to compute the CRC32 of '{$tarFile}'", $errorDetails));
        }
        // The trailer stores the CRC32 in little-endian order
        if ($crc !== bin2hex(strrev(substr($trailer, 0, 4)))) {
            throw new Exception\IO("The archive '{$archive}' is truncated or damaged");
        }
    }
}
