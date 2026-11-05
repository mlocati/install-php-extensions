<?php

namespace IPE\Filesystem\ArchiveExtractor\FormatExtractor;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor\FormatExtractor;

final class WithZipExtension extends FormatExtractor
{
    /**
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Filesystem\ArchiveExtractor\FormatExtractor\WithZipExtension|null
     */
    public static function createIfAvailable(Filesystem $fs, \IPE\Logger $logger)
    {
        if (!extension_loaded('zip')) {
            return null;
        }

        return new self($fs, $logger);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\ArchiveExtractor\FormatExtractor::doExtractTo()
     */
    protected function doExtractTo($archive, $destinationDirectory)
    {
        if ($this->fs->isLocal()) {
            $this->doExtractToLocally($archive, $destinationDirectory);
        } else {
            $this->doExtractToRemotely($archive, $destinationDirectory);
        }
    }

    /**
     * @param non-empty-string $archive the real path to an existing and readable file
     * @param non-empty-string $destinationDirectory the real path to an existing and writable directory
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function doExtractToLocally($archive, $destinationDirectory)
    {
        $this->logger->debug('Extracting archive with zip extension (locally)', ['archive' => $archive, 'destinationDirectory' => $destinationDirectory]);
        $zip = new \ZipArchive();
        try {
            $result = $zip->open($archive, \ZipArchive::CHECKCONS | (defined('ZipArchive::RDONLY') ? \ZipArchive::RDONLY : 0));
            if ($result !== true) {
                throw new Exception\IO("Failed to open file '{$archive}': " . ($zip->getStatusString() ?: $this->describeUnzipErrorCode($result)));
            }
            if (method_exists($zip, 'clearError')) { // @phpstan-ignore function.alreadyNarrowedType (the method requires PHP 8.2 or PECL zip 1.20)
                $zip->clearError();
            }
            if (!$zip->extractTo($destinationDirectory)) {
                throw new Exception\IO("Failed to extract file '{$archive}' to '{$destinationDirectory}': " . ($zip->getStatusString() ?: 'Unknown error'));
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @param non-empty-string $archive the real path to an existing and readable file
     * @param non-empty-string $destinationDirectory the real path to an existing and writable directory
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function doExtractToRemotely($archive, $destinationDirectory)
    {
        $this->logger->debug('Extracting archive with zip extension (copying files from/to remote system)', ['archive' => $archive, 'destinationDirectory' => $destinationDirectory]);
        $zipContents = $this->fs->readFile($archive);
        $localFS = new Filesystem\Local($this->logger);
        $tmp = $localFS->createVolatileDirectory();
        $tmpFile = $tmp->getPath() . '/file.zip';
        $localFS->writeFile($tmpFile, $zipContents);
        unset($zipContents);
        $zip = new \ZipArchive();
        try {
            $result = $zip->open($tmpFile, \ZipArchive::CHECKCONS | (defined('ZipArchive::RDONLY') ? \ZipArchive::RDONLY : 0));
            if ($result !== true) {
                throw new Exception\IO("Failed to open file '{$archive}': " . ($zip->getStatusString() ?: $this->describeUnzipErrorCode($result)));
            }
            if (method_exists($zip, 'clearError')) { // @phpstan-ignore function.alreadyNarrowedType (the method requires PHP 8.2 or PECL zip 1.20)
                $zip->clearError();
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if ($entryName === false) {
                    throw new Exception\IO("Failed to read the entry #{$i} of '{$archive}'");
                }
                $targetPath = $destinationDirectory . $localFS->getDirectorySeparator() . trim($entryName, '/');
                if (substr($entryName, -1) === '/') {
                    if (!$localFS->isDirectory($targetPath)) {
                        $localFS->createDirectory($targetPath);
                    }
                    continue;
                }
                $entryInfo = pathinfo($entryName);
                if (isset($entryInfo['dirname']) && $entryInfo['dirname'] !== '') {
                    $dirName = trim($entryInfo['dirname'], '/');
                    if ($dirName !== '.' && $dirName !== '..') {
                        $dir = $destinationDirectory . $localFS->getDirectorySeparator() . $dirName;
                        if (!$localFS->isDirectory($dir)) {
                            $localFS->createDirectory($dir);
                        }
                    }
                }
                $contents = $zip->getFromIndex($i);
                if ($contents === false) {
                    throw new Exception\IO("Failed to read ZIP entry {$entryName}");
                }
                $localFS->writeFile($targetPath, $contents);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @param int $errorCode
     *
     * @return string
     */
    private function describeUnzipErrorCode($errorCode)
    {
        if ($errorCode === \ZipArchive::ER_OK) {
            return '';
        }
        switch ($errorCode) {
            case \ZipArchive::ER_EXISTS:
                return 'File already exists';
            case \ZipArchive::ER_INCONS:
                return 'Zip archive inconsistent';
            case \ZipArchive::ER_INVAL:
                return 'Invalid argument';
            case \ZipArchive::ER_MEMORY:
                return 'Malloc failure';
            case \ZipArchive::ER_NOENT:
                return 'No such file';
            case \ZipArchive::ER_NOZIP:
                return 'Not a zip archive';
            case \ZipArchive::ER_OPEN:
                return "Can't open file";
            case \ZipArchive::ER_READ:
                return 'Read error';
            case \ZipArchive::ER_SEEK:
                return 'Seek error';
        }

        return "Unknown error (code: {$errorCode}";
    }
}
