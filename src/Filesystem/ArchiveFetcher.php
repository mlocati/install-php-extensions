<?php

namespace IPE\Filesystem;

use IPE\Exception;

/**
 * Downloads and extracts archives.
 */
final class ArchiveFetcher
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var \IPE\Filesystem\Downloader
     */
    private $downloader;

    /**
     * @var \IPE\Filesystem\ArchiveExtractor
     */
    private $archiveExtractor;

    public function __construct(Downloader $downloader, ArchiveExtractor $archiveExtractor, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->downloader = $downloader;
        $this->archiveExtractor = $archiveExtractor;
    }

    /**
     * @param non-empty-string $url
     * @param string $format one of the ArchiveFormat constants (empty string: detect it)
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     *
     * @return non-empty-string the root directory of the archive
     */
    public function fetch($url, $format, VolatileDirectory $volatileDir)
    {
        $fs = $volatileDir->getFilesystem();
        $downloadDir = $volatileDir->createSubdir();
        $archive = $downloadDir . $fs->getDirectorySeparator() . 'archive' . ($format === '' ? '' : ".{$format}");
        $this->logger->info("Downloading {$url}");
        $this->downloader->saveAs($url, $archive);
        // Some servers (like the ionCube one) reply to missing files with an HTML page instead of a 404 error
        if (strpos(ltrim($fs->readFile($archive, 256)), '<') === 0) {
            throw new Exception\IO\NotFound("The server replied to {$url} with a web page instead of an archive");
        }
        $extractTo = $volatileDir->createSubdir();
        $this->logger->debug("Extracting {$archive} to {$extractTo}");
        $rootDir = $this->archiveExtractor->extractTo($archive, $extractTo, $format);
        $fs->deleteDirectory($downloadDir);

        return $rootDir;
    }
}
