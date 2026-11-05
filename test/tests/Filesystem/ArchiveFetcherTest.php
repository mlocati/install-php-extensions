<?php

namespace IPE\Test\Filesystem;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor;
use IPE\Filesystem\ArchiveFetcher;
use IPE\Filesystem\ArchiveFormat;
use IPE\Filesystem\Downloader;
use IPE\Filesystem\VolatileDirectory;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ArchiveFetcherTest extends TestCase
{
    /**
     * @param string $downloaded
     *
     * @return \IPE\Filesystem\VolatileDirectory
     */
    private static function createVolatileDirectory($downloaded)
    {
        $fs = M::mock(Filesystem::class)->shouldIgnoreMissing();
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('readFile')->andReturn($downloaded);

        return new VolatileDirectory('/tmp/dir', $fs);
    }

    /**
     * @return void
     */
    public function testArchive()
    {
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('saveAs');
        $extractor = M::mock(ArchiveExtractor::class);
        $extractor->shouldReceive('extractTo')->andReturn('/tmp/dir/root');
        $fetcher = new ArchiveFetcher($downloader, $extractor, new \IPE\Logger());
        self::assertSame('/tmp/dir/root', $fetcher->fetch('https://example.com/a.tar.gz', ArchiveFormat::TAR_GZ, self::createVolatileDirectory("\x1F\x8B")));
    }

    /**
     * @return void
     */
    public function testWebPageInsteadOfArchive()
    {
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('saveAs');
        $fetcher = new ArchiveFetcher($downloader, M::mock(ArchiveExtractor::class), new \IPE\Logger());
        $this->expectException(Exception\IO\NotFound::class);
        $fetcher->fetch('https://example.com/a.tar.gz', ArchiveFormat::TAR_GZ, self::createVolatileDirectory("\n<!doctype html>"));
    }
}
