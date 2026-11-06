<?php

namespace IPE\Test\Installer;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Installer\PHPSourceProvider;
use IPE\Installer\State;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class PHPSourceProviderTest extends TestCase
{
    /**
     * @return list<array{non-empty-string, bool, list<string>}>
     */
    public static function provideDownloadedURLsCases()
    {
        $php = 'https://www.php.net/distributions/php-';
        $github = 'https://github.com/php/php-src/archive/refs/tags/php-';

        return [
            ['8.4.14', true, ["{$php}8.4.14.tar.xz", "{$github}8.4.14.tar.gz"]],
            ['8.4.14', false, ["{$php}8.4.14.tar.gz", "{$github}8.4.14.tar.gz"]],
            ['8.1.2-1ubuntu2.14', true, ["{$php}8.1.2.tar.xz", "{$github}8.1.2.tar.gz"]],
            ['7.4.33+deb11u1', true, ["{$php}7.4.33.tar.xz", "{$github}7.4.33.tar.gz"]],
            ['8.5.0RC1', true, ['https://downloads.php.net/~daniels/php-8.5.0RC1.tar.xz', 'https://downloads.php.net/~edorian/php-8.5.0RC1.tar.xz', "{$github}8.5.0RC1.tar.gz"]],
            ['7.4.0beta2', true, ["{$github}7.4.0beta2.tar.gz"]],
            ['8.6.0-dev', true, []],
        ];
    }

    /**
     * @dataProvider provideDownloadedURLsCases
     *
     * @param non-empty-string $phpVersion
     * @param bool $xzSupported
     * @param list<string> $expectedURLs
     *
     * @return void
     */
    public function testDownloadedURLs($phpVersion, $xzSupported, array $expectedURLs)
    {
        $downloadedURLs = [];
        $downloader = M::mock(Filesystem\Downloader::class);
        $downloader->shouldReceive('saveAs')->andReturnUsing(static function ($url) use (&$downloadedURLs) {
            $downloadedURLs[] = $url;
            throw new Exception\IO\NotFound('Not found');
        });
        $extractor = M::mock(Filesystem\ArchiveExtractor::class);
        $extractor->shouldReceive('canHandleFormat')->with(Filesystem\ArchiveFormat::TAR_XZ)->andReturn($xzSupported);
        $extractor->shouldReceive('extractTo')->never();
        try {
            (new PHPSourceProvider($downloader, $extractor))->providePHPSourceDirectory(self::createState($phpVersion));
            self::fail('The source code should not have been found');
        } catch (Exception\IO $x) {
            self::assertSame("Unable to find the source code of PHP {$phpVersion}", $x->getMessage());
        }
        self::assertSame($expectedURLs, $downloadedURLs);
    }

    /**
     * @return void
     */
    public function testFallbackToGitHub()
    {
        $downloader = M::mock(Filesystem\Downloader::class);
        $downloader->shouldReceive('saveAs')->once()->with('https://www.php.net/distributions/php-8.4.14.tar.xz', M::type('string'))->andThrow(new Exception\IO\NotFound('Not found'));
        $downloader->shouldReceive('saveAs')->once()->with('https://github.com/php/php-src/archive/refs/tags/php-8.4.14.tar.gz', '/tmp/v2/php-src1.tar.gz');
        $extractor = M::mock(Filesystem\ArchiveExtractor::class);
        $extractor->shouldReceive('canHandleFormat')->andReturn(true);
        $extractor->shouldReceive('extractTo')->once()->with('/tmp/v2/php-src1.tar.gz', '/tmp/v1/s1', Filesystem\ArchiveFormat::TAR_GZ)->andReturn('/tmp/v1/s1/php-src-php-8.4.14');
        $dir = (new PHPSourceProvider($downloader, $extractor))->providePHPSourceDirectory(self::createState('8.4.14'));
        self::assertSame('/tmp/v1/s1/php-src-php-8.4.14', $dir);
    }

    /**
     * @param non-empty-string $phpVersion
     *
     * @return \IPE\Installer\State
     */
    private static function createState($phpVersion)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getVersion')->andReturn($phpVersion);
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('resolveCommand')->with('docker-php-source')->andReturn('');
        $fs->shouldReceive('exists')->andReturn(false);
        $fs->shouldReceive('createDirectory');
        $fs->shouldReceive('deleteDirectory');
        $volatileDirs = 0;
        $fs->shouldReceive('createVolatileDirectory')->andReturnUsing(static function () use ($fs, &$volatileDirs) {
            $volatileDirs++;

            return new Filesystem\VolatileDirectory("/tmp/v{$volatileDirs}", $fs);
        });

        return new State($php, $fs);
    }
}
