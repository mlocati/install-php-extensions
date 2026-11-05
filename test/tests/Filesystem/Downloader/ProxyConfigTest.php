<?php

namespace IPE\Test\Filesystem\Downloader;

use IPE\Filesystem;
use IPE\Test\Support\DownloaderTest\ProxyDownloader;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ProxyConfigTest extends TestCase
{
    /**
     * @return array<array{string, string}>
     */
    public static function provideProxies()
    {
        return [
            ['http://proxy', 'http://proxy:80'],
            ['tcp://proxy', 'tcp://proxy:80'],
            ['https://proxy', 'https://proxy:443'],
            ['ssl://proxy', 'ssl://proxy:443'],
            ['HTTP://proxy', 'http://proxy:80'],
            ['HTTPS://proxy', 'https://proxy:443'],
            ['http://proxy:8080', 'http://proxy:8080'],
            ['https://proxy:8443', 'https://proxy:8443'],
        ];
    }

    /**
     * @dataProvider provideProxies
     *
     * @param string $proxy
     * @param string $expected
     *
     * @return void
     */
    public function testPort($proxy, $expected)
    {
        self::assertSame($expected, (new ProxyDownloader(['proxy' => $proxy], M::mock(Filesystem::class)))->getPublicProxyUrl());
    }
}
