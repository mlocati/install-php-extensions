<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Exception;
use IPE\Filesystem\Downloader;
use IPE\Filesystem\Local;

/**
 * A downloader that serves fixture files instead of hitting the network.
 */
final class FixtureDownloader implements Downloader
{
    /**
     * @public
     * @var string
     */
    const FORBIDDEN_BASENAME = 'forbidden.tar.gz';

    /**
     * @var \IPE\Filesystem\Local
     */
    private $fs;

    /**
     * @var non-empty-string
     */
    private $assetsDir;

    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private $map;

    /**
     * @param non-empty-string $assetsDir
     * @param array<non-empty-string, non-empty-string> $map URL => path of the fixture file, relative to $assetsDir (with forward slashes)
     */
    public function __construct(Local $fs, $assetsDir, array $map)
    {
        $this->fs = $fs;
        $this->assetsDir = $assetsDir;
        $this->map = $map;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::saveAs()
     */
    public function saveAs($url, $filename)
    {
        $this->fs->copyFile($this->resolve($url), $filename);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::getContent()
     */
    public function getContent($url)
    {
        return $this->fs->readFile($this->resolve($url));
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Downloader::getFilesystem()
     */
    public function getFilesystem()
    {
        return $this->fs;
    }

    /**
     * @param non-empty-string $url
     *
     * @throws \IPE\Exception\IO\NotFound
     * @throws \IPE\Exception\IO\Forbidden
     *
     * @return non-empty-string
     */
    private function resolve($url)
    {
        if (basename($url) === self::FORBIDDEN_BASENAME) {
            throw new Exception\IO\Forbidden("Forbidden: {$url}");
        }
        if (!isset($this->map[$url])) {
            throw new Exception\IO\NotFound("Not found: {$url}");
        }

        return $this->assetsDir . str_replace('/', DIRECTORY_SEPARATOR, '/' . $this->map[$url]);
    }
}
