<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\redis;
use IPE\Filesystem;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class RedisTest extends TestCase
{
    /**
     * @return list<array{int, string, bool, list<string>}>
     */
    public static function provideLZF()
    {
        return [
            // The system liblzf is used when available
            [324, '6.3.0', true, ['--enable-redis-lzf', '--with-liblzf', '--enable-redis-zstd', '--enable-redis-lz4', '--with-liblz4']],
            [324, '6.3.0', false, ['--enable-redis-lzf', '--with-liblzf', '--enable-redis-zstd', '--enable-redis-lz4', '--with-liblz4']],
            [324, '', false, ['--enable-redis-lzf', '--with-liblzf', '--enable-redis-zstd', '--enable-redis-lz4', '--with-liblz4']],
            // Otherwise the bundled one, if any
            [315, '6.3.0', true, ['--enable-redis-lzf', '--enable-redis-zstd', '--enable-redis-lz4', '--with-liblz4']],
            [315, '6.3.0', false, ['--enable-redis-zstd', '--enable-redis-lz4', '--with-liblz4']],
            // Before 5.3.0 the system liblzf may not be found
            [324, '5.2.0', true, ['--enable-redis-lzf', '--enable-redis-zstd']],
        ];
    }

    /**
     * @dataProvider provideLZF
     *
     * @param int $alpineVersion
     * @param string $version
     * @param bool $bundled
     * @param list<string> $expected
     *
     * @return void
     */
    public function testLZF($alpineVersion, $version, $bundled, array $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(803);
        $php->shouldReceive('getInstalledExtensions')->andReturn([]);
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('isFile')->with('/tmp/ipe/ext/liblzf/lzf.h')->andReturn($bundled);
        $os = M::mock(OS\Alpine::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getNumericVersion')->andReturn($alpineVersion);
        $redis = new redis($php, $os);
        self::assertSame($expected, $redis->getConfigureArguments(self::createBuildContext($version)));
        self::assertSame(in_array('--with-liblzf', $expected, true), in_array(SystemDependencies\liblzf::HANDLE, $redis->getRequiredSystemDependencies($version, new Values()), true));
    }
}
