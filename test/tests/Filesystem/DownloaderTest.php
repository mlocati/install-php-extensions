<?php

namespace IPE\Test\Filesystem;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Downloader;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class DownloaderTest extends TestCase
{
    const USINGPROXY_NO = 0;

    const USINGPROXY_YES_WITHOUT_AUTH = 1;

    const USINGPROXY_YES_WITH_AUTH = 2;

    /**
     * @var \IPE\Filesystem\Local
     */
    private static $fs;

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Test\Support\TestCaseBase::doSetUpBeforeClass()
     */
    public static function doSetUpBeforeClass()
    {
        self::$fs = self::createLocalFilesystem();
    }

    /**
     * @return list<array{int, array{proxy?: string}}>
     */
    public static function provideValidConfigCases()
    {
        return [
            [
                self::USINGPROXY_NO,
                [],
            ],
            [
                self::USINGPROXY_YES_WITHOUT_AUTH,
                [
                    'proxy' => 'http://127.0.0.1:' . IPE_TEST_PROXYSERVER_PORT,
                ],
            ],
            [
                self::USINGPROXY_YES_WITH_AUTH,
                [
                    'proxy' => 'http://ipe:pei@127.0.0.1:' . IPE_TEST_PROXYSERVER_PORT,
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideValidConfigCases
     *
     * @param int $usingProxy
     * @param array{proxy?: string} $config
     *
     * @return void
     */
    public function testCurlCommand($usingProxy, array $config)
    {
        $downloader = Downloader\WithCurlCommand::createIfAvailable($config, self::$fs, new \IPE\Logger());
        if (!self::isCurlCommandDownloaderAvailable()) {
            self::assertNull($downloader);

            return;
        }
        self::assertInstanceOf(Downloader\WithCurlCommand::class, $downloader);
        self::checkDownloader($downloader, $usingProxy);
    }

    /**
     * @dataProvider provideValidConfigCases
     *
     * @param int $usingProxy
     * @param array{proxy?: string} $config
     *
     * @return void
     */
    public function testCurlExtension($usingProxy, array $config)
    {
        $downloader = Downloader\WithCurlExtension::createIfAvailable($config, self::$fs, new \IPE\Logger());
        if (!self::isCurlExtensionDownloaderAvailable()) {
            self::assertNull($downloader);

            return;
        }
        self::assertInstanceOf(Downloader\WithCurlExtension::class, $downloader);
        self::checkDownloader($downloader, $usingProxy);
    }

    /**
     * @dataProvider provideValidConfigCases
     *
     * @param int $usingProxy
     * @param array{proxy?: string} $config
     *
     * @return void
     */
    public function testStreamWrapper($usingProxy, array $config)
    {
        $downloader = Downloader\WithStreamWrapper::createIfAvailable($config, self::$fs, new \IPE\Logger());
        if (!self::isStreamWrapperDownloaderAvailable()) {
            self::assertNull($downloader);

            return;
        }
        self::assertInstanceOf(Downloader\WithStreamWrapper::class, $downloader);
        self::checkDownloader($downloader, $usingProxy);
    }

    /**
     * @dataProvider provideValidConfigCases
     *
     * @param int $usingProxy
     * @param array{proxy?: string} $config
     *
     * @return void
     */
    public function testWgetCommand($usingProxy, array $config)
    {
        $downloaders = array_values(array_filter([
            Downloader\WithWgetCommand::createIfAvailable($config, self::$fs, new \IPE\Logger()),
            Downloader\WithBusyBoxWgetCommand::createIfAvailable($config, self::$fs, new \IPE\Logger()),
        ]));
        if (!self::isWgetCommandDownloaderAvailable()) {
            self::assertSame([], $downloaders);

            return;
        }
        // wget is either GNU wget or BusyBox wget
        self::assertCount(1, $downloaders);
        self::checkDownloader($downloaders[0], $usingProxy);
    }

    /**
     * @return list<array{array{cacheDir?: string, cacheMaxAge?: int<0, max>|non-empty-string, proxy?: string}, class-string<\Throwable>, string}>
     */
    public static function provideInvalidConfigCases()
    {
        return [
            [
                ['cacheDir' => __DIR__ . '/does-not-exist'],
                Exception\IO::class,
                'Failed to get the real path of',
            ],
            [
                ['proxy' => 'http://user@host'],
                Exception\InvalidArgument::class,
                'A proxy user was provided without a proxy password',
            ],
            [
                ['proxy' => 'http://:pwd@host'],
                Exception\InvalidArgument::class,
                'A proxy password was provided without a proxy user',
            ],
            [
                ['proxy' => 'foo'],
                Exception\InvalidArgument::class,
                'missing scheme',
            ],
            [
                ['proxy' => 'unknown://proxy'],
                Exception\InvalidArgument::class,
                'missing port',
            ],
            [
                ['proxy' => 'http://usr:@'],
                Exception\InvalidArgument::class,
                'Invalid argument $config[\'proxy\']',
            ],
            [
                ['proxy' => 'http://:pwd@'],
                Exception\InvalidArgument::class,
                'Invalid argument $config[\'proxy\']',
            ],
            [
                ['proxy' => 'http://usr:pwd@'],
                Exception\InvalidArgument::class,
                'Invalid argument $config[\'proxy\']',
            ],
            [
                ['proxy' => 'unknown://host'],
                Exception\InvalidArgument::class,
                'missing port',
            ],
            [
                ['proxy' => 'unknown://host:0'],
                Exception\InvalidArgument::class,
                'Invalid argument $config[\'proxy\']',
            ],
            [
                ['proxy' => 'unknown://host:65536'],
                Exception\InvalidArgument::class,
                'Invalid argument $config[\'proxy\']',
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidConfigCases
     *
     * @param array{cacheDir?: string, cacheMaxAge?: int<0, max>|non-empty-string, proxy?: string} $config
     * @param class-string<\Throwable> $expectedExceptionClass
     * @param string $expectedMessagePart
     *
     * @return void
     */
    public function testInvalidConfig(array $config, $expectedExceptionClass, $expectedMessagePart)
    {
        if (!self::isAnyDownloaderAvailable()) {
            self::markTestSkipped('No downloader available');
        }
        $factory = new Downloader\Factory(self::$fs, new \IPE\Logger(), $config);
        try {
            $factory->buildDownloader();
            self::fail('Expected exception not thrown');
        } catch (Exception $x) {
            self::assertInstanceOf($expectedExceptionClass, $x);
            self::assertStringContainsString($expectedMessagePart, $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testRetryingDownloader()
    {
        if (!self::isAnyDownloaderAvailable()) {
            self::markTestSkipped('No downloader available');
        }
        $downloader = (new Downloader\Factory(self::$fs, new \IPE\Logger(), ['retries' => 0]))->buildDownloader();
        self::assertNotInstanceOf(Downloader\Retrying::class, $downloader);
        $downloader = (new Downloader\Factory(self::$fs, new \IPE\Logger(), ['retries' => 2, 'retryDelay' => 0]))->buildDownloader();
        self::assertInstanceOf(Downloader\Retrying::class, $downloader);
        self::assertSame('This the content of file #1!', $downloader->getContent('http://127.0.0.1:' . IPE_TEST_WEBSERVER_PORT . '/file1'));
        $cache = self::$fs->createVolatileDirectory();
        $downloader = (new Downloader\Factory(self::$fs, new \IPE\Logger(), ['retries' => 2, 'cacheDir' => $cache->getPath()]))->buildDownloader();
        self::assertInstanceOf(Downloader\Cached::class, $downloader);
    }

    /**
     * @return void
     */
    public function testCachedDownloader()
    {
        if (!self::isAnyDownloaderAvailable()) {
            self::markTestSkipped('No downloader available');
        }
        $fs = self::$fs;
        $cache = $fs->createVolatileDirectory();
        $expectedCacheContent = [];
        $target = $fs->createVolatileDirectory();
        $expectedTargetContent = [];
        $factory = new Downloader\Factory($fs, new \IPE\Logger(), ['cacheDir' => $cache->getPath()]);
        $downloader = $factory->buildDownloader();
        self::assertInstanceOf(Downloader\Cached::class, $downloader);

        $url = 'http://127.0.0.1:' . IPE_TEST_WEBSERVER_PORT . '/file1';
        $expectedCacheContent[] = 'http-127-0-0-1-' . IPE_TEST_WEBSERVER_PORT . '-file1-' . md5($url);
        for ($cycle = 0; $cycle < 2; $cycle++) {
            $content = $downloader->getContent('http://127.0.0.1:' . IPE_TEST_WEBSERVER_PORT . '/file1');
            self::assertSame('This the content of file #1!', $content);
            self::assertSame($expectedTargetContent, $fs->listDirectoryContents($target->getPath()));
            self::assertSame($expectedCacheContent, $fs->listDirectoryContents($cache->getPath()));
        }

        $url = 'http://127.0.0.1:' . IPE_TEST_WEBSERVER_PORT . '/file2';
        $targetFile = $target->getPath() . '/downloaded2';
        $expectedCacheContent[] = 'http-127-0-0-1-' . IPE_TEST_WEBSERVER_PORT . '-file2-' . md5($url);
        $expectedTargetContent[] = 'downloaded2';
        for ($cycle = 0; $cycle < 2; $cycle++) {
            $downloader->saveAs($url, $targetFile);
            $content = $fs->readFile($targetFile);
            self::assertSame('This the content of file #2!', $content);
            self::assertSame($expectedTargetContent, $fs->listDirectoryContents($target->getPath()));
            self::assertSame($expectedCacheContent, $fs->listDirectoryContents($cache->getPath()));
        }
    }

    /**
     * @dataProvider cachedDownloaderWith404Cases
     *
     * @param bool $firstTime
     *
     * @return void
     */
    public function testCachedDownloaderWith404($firstTime)
    {
        if (!self::isAnyDownloaderAvailable()) {
            self::markTestSkipped('No downloader available');
        }
        $url = 'https://test.notfound.org/file';
        $cachedFilename = '/cache/https-test-notfound-org-file-' . md5($url);
        $error404Message = 'Message of 404 Not Found exception thrown by the actual downloader';
        $error404Serialized = Downloader\Cached::NOTFOUND_FILECONTENTS . $error404Message;
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('getRealPath')->once()->with('../cache')->andReturn('/cache');
        $fs->shouldReceive('isDirectory')->once()->with('/cache')->andReturn(true);
        $fs->shouldReceive('isWritable')->once()->with('/cache')->andReturn(true);
        $actualDownloader = M::mock(Downloader::class);
        $actualDownloader->shouldReceive('getFilesystem')->andReturn($fs);
        if ($firstTime) {
            $fs->shouldReceive('isFile')->once()->with($cachedFilename)->andReturn(false);
            $actualDownloader->shouldReceive('getContent')->with($url)->andThrow(new Exception\IO\NotFound($error404Message));
            $fs->shouldReceive('writeFile')->once()->with(self::temporaryFileOf($cachedFilename), $error404Serialized);
            $fs->shouldReceive('rename')->once()->with(self::temporaryFileOf($cachedFilename), $cachedFilename);
        } else {
            $fs->shouldReceive('isFile')->once()->with($cachedFilename)->andReturn(true);
            $fs->shouldReceive('readFile')->once()->with($cachedFilename)->andReturn($error404Serialized);
        }
        $cachedDownloader = new Downloader\Cached($actualDownloader, new \IPE\Logger(), '../cache');
        try {
            $cachedDownloader->getContent('https://test.notfound.org/file');
            self::fail('Expected exception not thrown');
        } catch (Exception\IO\NotFound $x) {
            self::assertSame($x->getMessage(), $error404Message);
        }
    }

    /**
     * @return void
     */
    public function testInterruptedDownloadIsNotCached()
    {
        $fs = self::$fs;
        $cache = $fs->createVolatileDirectory();
        $target = $fs->createVolatileDirectory();
        $url = 'https://test.example.org/file';
        $actualDownloader = M::mock(Downloader::class);
        $actualDownloader->shouldReceive('getFilesystem')->andReturn($fs);
        $actualDownloader->shouldReceive('saveAs')->once()->with($url, M::type('string'))->andReturnUsing(static function ($url, $filename) use ($fs) {
            if (is_string($filename) && $filename !== '') {
                $fs->writeFile($filename, 'partial content');
            }

            throw new Exception\IO('Connection reset');
        });
        $cachedDownloader = new Downloader\Cached($actualDownloader, new \IPE\Logger(), $cache->getPath());
        try {
            $cachedDownloader->saveAs($url, $target->getPath() . '/file');
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertSame('Connection reset', $x->getMessage());
        }
        self::assertSame([], $fs->listDirectoryContents($cache->getPath()));
        self::assertSame([], $fs->listDirectoryContents($target->getPath()));
    }

    /**
     * @return array<string, array{non-negative-int, int, bool}>
     */
    public static function cachedContentCases()
    {
        return [
            'never expires' => [0, 1000000, false],
            'not expired' => [60, 10, false],
            'expired' => [60, 1000, true],
        ];
    }

    /**
     * @dataProvider cachedContentCases
     *
     * @param non-negative-int $maxAge
     * @param int $age
     * @param bool $expectDownload
     *
     * @return void
     */
    public function testCachedContentIsNotDownloadedAgain($maxAge, $age, $expectDownload)
    {
        $url = 'https://test.example.org/file';
        $cachedFilename = '/cache/https-test-example-org-file-' . md5($url);
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('getRealPath')->with('../cache')->andReturn('/cache');
        $fs->shouldReceive('isDirectory')->with('/cache')->andReturn(true);
        $fs->shouldReceive('isWritable')->with('/cache')->andReturn(true);
        $fs->shouldReceive('isFile')->with($cachedFilename)->andReturn(true);
        $fs->shouldReceive('getLastModificationTimestamp')->with($cachedFilename)->andReturn(time() - $age);
        $actualDownloader = M::mock(Downloader::class);
        $actualDownloader->shouldReceive('getFilesystem')->andReturn($fs);
        if ($expectDownload) {
            $actualDownloader->shouldReceive('getContent')->once()->with($url)->andReturn('new content');
            $fs->shouldReceive('writeFile')->once()->with(self::temporaryFileOf($cachedFilename), 'new content');
            $fs->shouldReceive('rename')->once()->with(self::temporaryFileOf($cachedFilename), $cachedFilename);
            $expectedContent = 'new content';
        } else {
            $actualDownloader->shouldNotReceive('getContent');
            $fs->shouldReceive('readFile')->once()->with($cachedFilename)->andReturn('cached content');
            $expectedContent = 'cached content';
        }
        self::assertSame($expectedContent, (new Downloader\Cached($actualDownloader, new \IPE\Logger(), '../cache', $maxAge))->getContent($url));
    }

    /**
     * @return array{bool}[]
     */
    public static function cachedDownloaderWith404Cases()
    {
        return [
            [true],
            [false],
        ];
    }

    /**
     * @param int $usingProxy
     *
     * @return void
     */
    private static function checkDownloader(Downloader $downloader, $usingProxy)
    {
        self::assertSame(self::$fs, $downloader->getFilesystem());
        if ($usingProxy === self::USINGPROXY_NO) {
            self::checkDownloaderWithoutProxy($downloader);
        } else {
            self::checkDownloaderWithProxy($downloader, $usingProxy === self::USINGPROXY_YES_WITH_AUTH);
        }
    }

    /**
     * @return void
     */
    private static function checkDownloaderWithoutProxy(Downloader $downloader)
    {
        try {
            $downloader->getContent('http://127.0.0.1:' . IPE_TEST_WEBSERVER_PORT . '/does-not-exist');
            self::fail('Expected exception not thrown');
        } catch (\Exception $x) {
            self::assertInstanceOf(Exception\IO\NotFound::class, $x, "Actual exception message: {$x->getMessage()}");
            self::assertStringContainsString('404', $x->getMessage());
        }
        try {
            $downloader->getContent('http://127.0.0.1:' . IPE_TEST_WEBSERVER_PORT . '/forbidden.php');
            self::fail('Expected exception not thrown');
        } catch (\Exception $x) {
            self::assertInstanceOf(Exception\IO\Forbidden::class, $x, "Actual exception message: {$x->getMessage()}");
        }
        $content = $downloader->getContent('http://127.0.0.1:' . IPE_TEST_WEBSERVER_PORT . '/file1');
        self::assertSame('This the content of file #1!', $content);
        $userAgent = $downloader->getContent('http://127.0.0.1:' . IPE_TEST_WEBSERVER_PORT . '/dump-useragent.php');
        self::assertMatchesRegularExpression('#^IPE-\w+ ' . preg_quote('(+https://github.com/mlocati/install-php-extensions)', '#') . '$#D', $userAgent);
    }

    /**
     * @param bool $clientHasAuth
     *
     * @return void
     */
    private static function checkDownloaderWithProxy(Downloader $downloader, $clientHasAuth)
    {
        $fs = $downloader->getFilesystem();
        $tmpDir = $fs->createVolatileDirectory();
        $tmpFile = $tmpDir->getPath() . '/file';
        $content = $downloader->getContent('http://127.0.0.1:123/unprotected');
        self::assertSame('Content of unprotected', $content);
        $downloader->saveAs('http://127.0.0.1:123/unprotected', $tmpFile);
        $content = $fs->readFile($tmpFile);
        self::assertSame('Content of unprotected', $content);
        if ($clientHasAuth) {
            $content = $downloader->getContent('http://127.0.0.1:123/protected');
            self::assertSame('Content of protected', $content);
            $downloader->saveAs('http://127.0.0.1:123/protected', $tmpFile);
            $content = $fs->readFile($tmpFile);
            self::assertSame('Content of protected', $content);
        } else {
            try {
                $downloader->getContent('http://127.0.0.1:123/protected');
                self::fail('Expected exception not thrown');
            } catch (Exception\IO $x) {
                self::assertStringContainsString('407', $x->getMessage());
            }
        }
    }

    /**
     * @return bool
     */
    private static function isCurlCommandDownloaderAvailable()
    {
        /** @var bool|null $result */
        static $result;
        if ($result === null) {
            $result = self::$fs->resolveCommand('curl') !== '';
        }

        return $result;
    }

    /**
     * @return bool
     */
    private static function isCurlExtensionDownloaderAvailable()
    {
        /** @var bool|null $result */
        static $result;
        if ($result === null) {
            $result = extension_loaded('curl');
        }

        return $result;
    }

    /**
     * @return bool
     */
    private static function isStreamWrapperDownloaderAvailable()
    {
        /** @var bool|null $result */
        static $result;
        if ($result === null) {
            $streamWrappers = array_map('strtolower', stream_get_wrappers());
            $result = in_array('http', $streamWrappers, true) && in_array('https', $streamWrappers, true);
        }

        return $result;
    }

    /**
     * @return bool
     */
    private static function isWgetCommandDownloaderAvailable()
    {
        /** @var bool|null $result */
        static $result;
        if ($result === null) {
            $result = self::$fs->resolveCommand('wget') !== '';
        }

        return $result;
    }

    /**
     * @return bool
     */
    private static function isAnyDownloaderAvailable()
    {
        return self::isCurlCommandDownloaderAvailable()
            || self::isCurlExtensionDownloaderAvailable()
            || self::isStreamWrapperDownloaderAvailable()
            || self::isWgetCommandDownloaderAvailable();
    }

    /**
     * @param non-empty-string $cachedFilename
     *
     * @return \Mockery\Matcher\Closure
     */
    private static function temporaryFileOf($cachedFilename)
    {
        $pattern = '/^' . preg_quote($cachedFilename, '/') . '\.[\w\-]+\.tmp$/D';

        // Mockery 0.9 (used with PHP < 7.3) doesn't have M::pattern()
        return M::on(static function ($value) use ($pattern) {
            return is_string($value) && preg_match($pattern, $value) === 1;
        });
    }
}
