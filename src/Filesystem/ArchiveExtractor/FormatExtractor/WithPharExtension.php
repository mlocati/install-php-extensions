<?php

namespace IPE\Filesystem\ArchiveExtractor\FormatExtractor;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor\FormatExtractor;
use IPE\Filesystem\ArchiveFormat;
use IPE\Traits;

final class WithPharExtension extends FormatExtractor
{
    use Traits\WarningCatcher;

    /**
     * @var \IPE\Filesystem\ArchiveFormat::TAR|\IPE\Filesystem\ArchiveFormat::TAR_BZ|\IPE\Filesystem\ArchiveFormat::TAR_GZ|\IPE\Filesystem\ArchiveFormat::ZIP
     */
    private $format;

    /**
     * @param \IPE\Filesystem\ArchiveFormat::TAR|\IPE\Filesystem\ArchiveFormat::TAR_BZ|\IPE\Filesystem\ArchiveFormat::TAR_GZ|\IPE\Filesystem\ArchiveFormat::ZIP $format
     */
    protected function __construct(Filesystem $fs, \IPE\Logger $logger, $format)
    {
        parent::__construct($fs, $logger);
        $this->format = $format;
    }

    /**
     * @param non-empty-string $format
     *
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Filesystem\ArchiveExtractor\FormatExtractor\WithPharExtension|null
     */
    public static function createIfAvailable($format, Filesystem $fs, \IPE\Logger $logger)
    {
        if (!extension_loaded('Phar')) {
            return null;
        }
        if ($format === ArchiveFormat::TAR) {
            return new self($fs, $logger, $format);
        }
        if ($format === ArchiveFormat::TAR_BZ) {
            return array_intersect(['BZIP2', \Phar::BZ2], \Phar::getSupportedCompression()) === [] ? null : new self($fs, $logger, $format);
        }
        if ($format === ArchiveFormat::TAR_GZ || $format === ArchiveFormat::ZIP) {
            // zip entries are usually deflated, which requires zlib
            return array_intersect(['GZ', \Phar::GZ], \Phar::getSupportedCompression()) === [] ? null : new self($fs, $logger, $format);
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
        if ($this->format === ArchiveFormat::ZIP) {
            $this->logger->debug('Extracting archive with Phar extension', ['archive' => $archive, 'destination' => $destinationDirectory]);
            $this->extractZipEntries($this->openArchive($archive, \Phar::ZIP), $destinationDirectory);

            return;
        }
        if ($this->format === ArchiveFormat::TAR) {
            $this->logger->debug('Extracting archive with Phar extension', ['archive' => $archive, 'destination' => $destinationDirectory]);
            $this->extractPharData($this->openArchive($archive, \Phar::TAR), $destinationDirectory);

            return;
        }
        $tmp = $this->fs->createVolatileDirectory();
        $compressedFile = $tmp->getPath() . '/archive.' . $this->format;
        $this->fs->copyFile($archive, $compressedFile);
        $this->logger->debug('Decompressing archive with Phar extension', ['compressedFile' => $compressedFile]);
        $compressedPhar = $this->openArchive($compressedFile, \Phar::TAR);
        try {
            list($decompressedPhar, $errorDetails) = self::wrap(
                static function () use ($compressedPhar) {
                    return $compressedPhar->decompress();
                }
            );
        } catch (\BadMethodCallException $x) {
            throw new Exception\IO($x->getMessage());
        }
        if ($errorDetails !== '') {
            throw new Exception\IO(self::buildErrorMessage("Failed to decompress compressed tar file '{$archive}'", $errorDetails));
        }
        $this->logger->debug('Extracting decompressed archive with Phar extension', ['destinationDirectory' => $destinationDirectory]);
        $this->extractPharData($decompressedPhar, $destinationDirectory);
    }

    /**
     * @param non-empty-string $file
     * @param int $pharFormat \Phar::TAR or \Phar::ZIP
     *
     * @throws \IPE\Exception\IO
     *
     * @return \PharData
     */
    private function openArchive($file, $pharFormat)
    {
        try {
            return new \PharData($file, \FilesystemIterator::UNIX_PATHS, null, $pharFormat);
        } catch (\UnexpectedValueException $x) {
            throw new Exception\IO($x->getMessage());
        } catch (\BadMethodCallException $x) {
            throw new Exception\IO($x->getMessage());
        }
    }

    /**
     * Before PHP 7.4, PharData::extractTo() doesn't decompress the entries of zip archives.
     *
     * @param non-empty-string $destinationDirectory
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function extractZipEntries(\PharData $phar, $destinationDirectory)
    {
        $separator = $this->fs->getDirectorySeparator();
        try {
            $entries = new \RecursiveIteratorIterator($phar, \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($entries as $entry) {
                $directory = $entries->getInnerIterator();
                if (!$entry instanceof \PharFileInfo || !$directory instanceof \RecursiveDirectoryIterator) {
                    throw new Exception\IO('Unexpected entry in the zip archive');
                }
                $path = $destinationDirectory . $separator . str_replace('/', $separator, $directory->getSubPathname());
                if ($entry->isDir()) {
                    if (!$this->fs->isDirectory($path)) {
                        $this->fs->createDirectory($path);
                    }
                } else {
                    $this->fs->writeFile($path, $entry->getContent());
                }
            }
        } catch (\RuntimeException $x) {
            throw new Exception\IO($x->getMessage());
        }
    }

    /**
     * @param non-empty-string $destinationDirectory
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function extractPharData(\PharData $phar, $destinationDirectory)
    {
        try {
            list($result, $errorDetails) = self::wrap(
                static function () use ($phar, $destinationDirectory) {
                    return $phar->extractTo($destinationDirectory, null, true);
                }
            );
        } catch (\PharException $x) {
            throw new Exception\IO(self::buildErrorMessage("Failed to extract tar to '{$destinationDirectory}'", $x->getMessage()));
        }
        if ($result === false) {
            throw new Exception\IO(self::buildErrorMessage("Failed to extract tar to '{$destinationDirectory}'", $errorDetails));
        }
    }
}
