<?php

namespace IPE\Filesystem\ArchiveExtractor\FormatExtractor;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor\FormatExtractor;
use IPE\Filesystem\ArchiveFormat;
use IPE\Traits;

/**
 * It doesn't handle compressed tar archives: PharData loads them uncompressed in memory, and PharData::decompress() rejects some long names (see WithStreamWrapper).
 */
final class WithPharExtension extends FormatExtractor
{
    use Traits\WarningCatcher;

    /**
     * @var \IPE\Filesystem\ArchiveFormat::TAR|\IPE\Filesystem\ArchiveFormat::ZIP
     */
    private $format;

    /**
     * @param \IPE\Filesystem\ArchiveFormat::TAR|\IPE\Filesystem\ArchiveFormat::ZIP $format
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
        if ($format === ArchiveFormat::ZIP) {
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
        $this->logger->debug('Extracting archive with Phar extension', ['archive' => $archive, 'destination' => $destinationDirectory]);
        $this->extractPharData($this->openArchive($archive, \Phar::TAR), $destinationDirectory);
        // PharData::extractTo() sets the current time
        $this->restoreModificationTimes($archive, $destinationDirectory);
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
                    $mtime = $entry->getMTime();
                    if ($mtime > 0) {
                        $this->fs->setLastModificationTimestamp($path, $mtime);
                    }
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

    /**
     * The times are read from the tar headers: PharData can't access the entries with GNU long names (like some in the PHP source archives).
     *
     * @param non-empty-string $archive
     * @param non-empty-string $destinationDirectory
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function restoreModificationTimes($archive, $destinationDirectory)
    {
        $separator = $this->fs->getDirectorySeparator();
        foreach (self::readTarModificationTimes($archive) as $name => $mtime) {
            $path = $destinationDirectory . $separator . str_replace('/', $separator, $name);
            if ($this->fs->isFile($path)) {
                $this->fs->setLastModificationTimestamp($path, $mtime);
            }
        }
    }

    /**
     * @param non-empty-string $archive
     *
     * @throws \IPE\Exception\IO
     *
     * @return array<non-empty-string, positive-int> the modification times of the regular files, keyed by their relative path
     */
    private static function readTarModificationTimes($archive)
    {
        list($handle, $errorDetails) = self::wrap(
            static function () use ($archive) {
                return fopen($archive, 'rb');
            },
            'fopen'
        );
        if ($handle === false) {
            throw new Exception\IO(self::buildErrorMessage("Failed to open the archive '{$archive}'", $errorDetails));
        }
        $result = [];
        try {
            $longName = null;
            $pax = [];
            for (;;) {
                $header = fread($handle, 512);
                if (!is_string($header) || strlen($header) !== 512 || trim($header, "\0") === '') {
                    break;
                }
                $type = $header[156];
                $size = self::parseTarNumber(substr($header, 124, 12));
                $data = '';
                if ($type === 'L' || $type === 'x') {
                    $data = $size > 0 ? (string) fread($handle, $size) : '';
                    $padding = (512 - $size % 512) % 512;
                } else {
                    $padding = $size > 0 ? (int) ceil($size / 512) * 512 : 0;
                }
                if ($padding > 0 && fseek($handle, $padding, SEEK_CUR) !== 0) {
                    throw new Exception\IO("Failed to read the archive '{$archive}'");
                }
                if ($type === 'L') {
                    // GNU long name of the next entry
                    $longName = rtrim($data, "\0");
                    continue;
                }
                if ($type === 'x') {
                    // POSIX extended header of the next entry
                    $pax = self::parsePaxRecords($data);
                    continue;
                }
                if ($longName !== null) {
                    $name = $longName;
                } elseif (isset($pax['path'])) {
                    $name = $pax['path'];
                } else {
                    $name = rtrim(substr($header, 0, 100), "\0");
                    $prefix = rtrim(substr($header, 345, 155), "\0");
                    // GNU tar uses the prefix field for other data
                    if ($prefix !== '' && substr($header, 257, 6) === "ustar\0") {
                        $name = "{$prefix}/{$name}";
                    }
                }
                $mtime = isset($pax['mtime']) ? (int) $pax['mtime'] : self::parseTarNumber(substr($header, 136, 12));
                $longName = null;
                $pax = [];
                $name = (string) preg_replace('_^(\./)+_', '', $name);
                if ($name === '' || $name[0] === '/' || in_array('..', explode('/', $name), true)) {
                    continue;
                }
                // Regular and contiguous files
                if ($mtime > 0 && ($type === '0' || $type === "\0" || $type === '7')) {
                    $result[$name] = $mtime;
                } else {
                    // A later entry (like a symbolic link) replaces the file
                    unset($result[$name]);
                }
            }
        } finally {
            fclose($handle);
        }

        return $result;
    }

    /**
     * @param string $field
     *
     * @throws \IPE\Exception\IO
     *
     * @return int
     */
    private static function parseTarNumber($field)
    {
        $value = trim($field, " \0");
        if ($value === '') {
            return 0;
        }
        // The base-256 numbers of GNU tar are only needed for files bigger than 8 GB
        if (!preg_match('/^[0-7]+$/', $value)) {
            throw new Exception\IO('Unsupported number in the tar header: ' . bin2hex($field));
        }

        return (int) octdec($value);
    }

    /**
     * @param string $data records like "30 mtime=1432668052.123456789\n"
     *
     * @return array<string, string>
     */
    private static function parsePaxRecords($data)
    {
        $result = [];
        $offset = 0;
        $length = strlen($data);
        while ($offset < $length) {
            $space = strpos($data, ' ', $offset);
            if ($space === false) {
                break;
            }
            $recordLength = (int) substr($data, $offset, $space - $offset);
            if ($recordLength <= $space - $offset) {
                break;
            }
            $record = substr($data, $space + 1, $recordLength - ($space - $offset) - 2);
            $equals = strpos($record, '=');
            if ($equals !== false) {
                $result[substr($record, 0, $equals)] = substr($record, $equals + 1);
            }
            $offset += $recordLength;
        }

        return $result;
    }
}
