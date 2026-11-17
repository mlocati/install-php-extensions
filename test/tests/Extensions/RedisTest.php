<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\redis;
use IPE\Filesystem;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class RedisTest extends TestCase
{
    /**
     * @return list<array{bool, list<string>}>
     */
    public static function provideBundledLZF()
    {
        return [
            [true, ['--enable-redis-lzf', '--enable-redis-zstd', '--enable-redis-lz4', '--with-liblz4']],
            [false, ['--enable-redis-zstd', '--enable-redis-lz4', '--with-liblz4']],
        ];
    }

    /**
     * @dataProvider provideBundledLZF
     *
     * @param bool $bundled
     * @param list<string> $expected
     *
     * @return void
     */
    public function testBundledLZF($bundled, array $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(803);
        $php->shouldReceive('getInstalledExtensions')->andReturn([]);
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('isFile')->with('/tmp/ipe/ext/liblzf/lzf.h')->andReturn($bundled);
        $os = M::mock(OS\Alpine::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        self::assertSame($expected, (new redis($php, $os))->getConfigureArguments(self::createBuildContext('6.3.0')));
    }
}
