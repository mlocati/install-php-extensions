<?php

namespace IPE\Filesystem\Downloader;

use IPE\Exception;
use IPE\Filesystem\Downloader;
use IPE\Service\Regex;
use IPE\Service\Url;

final class Cached implements Downloader
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @internal
     * @var string
     */
    const NOTFOUND_FILECONTENTS = "IPE/404\n";

    /**
     * @var \IPE\Filesystem\Downloader
     */
    private $actualDownloader;

    /**
     * @var string
     */
    private $cacheDirectory;

    /**
     * @var non-negative-int
     */
    private $maxAge;

    /**
     * @param non-empty-string $cacheDirectory
     * @param non-negative-int|non-empty-string $maxAge
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     */
    public function __construct(Downloader $actualDownloader, \IPE\Logger $logger, $cacheDirectory, $maxAge = 0)
    {
        $this->logger = $logger;
        $this->actualDownloader = $actualDownloader;
        $fs = $actualDownloader->getFilesystem();
        $cacheDirectory = $fs->getRealPath($cacheDirectory);
        if (!$fs->isDirectory($cacheDirectory)) {
            throw new Exception\InvalidArgument("'{$cacheDirectory}' is not a directory");
        }
        if (!$fs->isWritable($cacheDirectory)) {
            throw new Exception\InvalidArgument("The directory '{$cacheDirectory}' is not writable");
        }
        $this->cacheDirectory = $cacheDirectory;
        $matches = null;
        if (is_string($maxAge) && preg_match('/^(?<num>\d{1,8})\s*(?<unit>[smhd]?)$/D', $maxAge, $matches)) {
            $mul = [
                '' => 1,
                's' => 1,
                'm' => 60,
                'h' => 3600,
                'd' => 86400,
            ];
            $maxAge = (int) $matches['num'] * $mul[$matches['unit']];
        }
        if (!is_int($maxAge) || $maxAge < 0) {
            throw new Exception\InvalidArgument("The cache max age '{$maxAge}' is not valid (it must be a non negative integer, optionally followed by 's' (seconds), 'm' (minutes), 'h' (hours), or , 'd' (days)");
        }
        $this->maxAge = $maxAge;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::saveAs()
     */
    public function saveAs($url, $filename)
    {
        $url = Url::validate($url);
        $cachedFilename = $this->buildCachedFilename($url);
        $fs = $this->getFilesystem();
        $age = null;
        if ($fs->isFile($cachedFilename)) {
            $age = time() - $fs->getLastModificationTimestamp($cachedFilename);
        }
        if ($age !== null && ($this->maxAge === 0 || $age <= $this->maxAge)) {
            $this->logger->debug('Using cached file instead of downloading', ['url' => $url, 'cacheFile' => $cachedFilename]);
        } else {
            $this->logger->debug('Saving content of URL to cache file', ['url' => $url, 'cacheFile' => $cachedFilename]);
            $actualDownloader = $this->actualDownloader;
            $this->writeCacheFile($cachedFilename, static function ($temporaryFilename) use ($actualDownloader, $url) {
                $actualDownloader->saveAs($url, $temporaryFilename);
            });
        }
        $fs->copyFile($cachedFilename, $filename);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::getContent()
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\IO\NotFound
     */
    public function getContent($url)
    {
        $url = Url::validate($url);
        $cachedFilename = $this->buildCachedFilename($url);
        $fs = $this->getFilesystem();
        $found = false;
        if ($fs->isFile($cachedFilename)) {
            $found = true;
            if ($this->maxAge !== 0) {
                $age = time() - $fs->getLastModificationTimestamp($cachedFilename);
                if ($age > $this->maxAge) {
                    $this->logger->debug("The age of the cached file ({$age} secs) is greater than the maximum cache age ({$this->maxAge} secs).");
                    $found = false;
                }
            }
        }
        if ($found) {
            $this->logger->debug('Using cached file instead of downloading', ['url' => $url, 'cacheFile' => $cachedFilename]);
            $contents = $fs->readFile($cachedFilename);
            if (substr($contents, 0, strlen(self::NOTFOUND_FILECONTENTS)) === self::NOTFOUND_FILECONTENTS) {
                throw new Exception\IO\NotFound(substr($contents, strlen(self::NOTFOUND_FILECONTENTS)));
            }

            return $contents;
        }
        $this->logger->debug('Saving content of URL to cache file', ['url' => $url, 'cacheFile' => $cachedFilename]);
        try {
            $content = $this->actualDownloader->getContent($url);
        } catch (Exception\IO\NotFound $x) {
            $this->writeCacheFileContent($cachedFilename, self::NOTFOUND_FILECONTENTS . $x->getMessage());

            throw $x;
        }
        $this->writeCacheFileContent($cachedFilename, $content);

        return $content;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::getFilesystem()
     */
    public function getFilesystem()
    {
        return $this->actualDownloader->getFilesystem();
    }

    /**
     * @param non-empty-string $cachedFilename
     * @param string $content
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function writeCacheFileContent($cachedFilename, $content)
    {
        $fs = $this->getFilesystem();
        $this->writeCacheFile($cachedFilename, static function ($temporaryFilename) use ($fs, $content) {
            $fs->writeFile($temporaryFilename, $content);
        });
    }

    /**
     * Write to a temporary file in the cache directory, and then rename it: an interrupted download or a concurrent process never leave a partial cache file.
     *
     * @param non-empty-string $cachedFilename
     * @param callable(non-empty-string): void $write
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function writeCacheFile($cachedFilename, $write)
    {
        $fs = $this->getFilesystem();
        $temporaryFilename = $cachedFilename . '.' . str_replace('.', '-', uniqid('', true)) . '.tmp';
        $renamed = false;
        try {
            $write($temporaryFilename);
            $fs->rename($temporaryFilename, $cachedFilename);
            $renamed = true;
        } finally {
            if (!$renamed && $fs->isFile($temporaryFilename)) {
                $fs->deleteFile($temporaryFilename);
            }
        }
    }

    /**
     * @param string $url
     *
     * @return non-empty-string
     */
    private function buildCachedFilename($url)
    {
        $filename = trim(Regex::replace('/\W+/', '-', $url), '-') . '-' . md5($url);
        $sep = $this->getFilesystem()->getDirectorySeparator();

        return rtrim($this->cacheDirectory, $sep) . $sep . $filename;
    }
}
