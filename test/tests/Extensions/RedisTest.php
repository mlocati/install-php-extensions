<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\redis;
use IPE\Filesystem;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies;
use IPE\SystemPackager\Homebrew;
use IPE\SystemPackager\Package;
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

    /**
     * @return void
     */
    public function testLZFOnMacOS()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(804);
        $php->shouldReceive('getInstalledExtensions')->andReturn([]);
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->once()->andReturn([0, "/opt/homebrew\n"]);
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getSystemPackager')->andReturn(new Homebrew($os));
        self::assertSame(
            ['--enable-redis-lzf', '--with-liblzf=/opt/homebrew/opt/liblzf', '--enable-redis-zstd', '--enable-redis-lz4', '--with-liblz4'],
            (new redis($php, $os))->getConfigureArguments(self::createBuildContext('6.3.0'))
        );
    }

    /**
     * @return void
     */
    public function testSystemPackageNames()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->once()->andReturn([0, (string) json_encode([
            ['name' => 'shivammathur/extensions', 'installed' => true, 'trusted' => true, 'formula_names' => ['shivammathur/extensions/phpredis@8.4']],
            ['name' => 'shivammathur/php', 'installed' => true, 'trusted' => true, 'formula_names' => ['shivammathur/php/php@8.4']],
        ])]);
        $macOS = M::mock(OS\MacOS::class);
        $macOS->shouldReceive('getFilesystem')->andReturn($fs);
        $macOS->shouldReceive('getSystemPackager')->andReturn(new Homebrew($macOS));
        self::assertSame(['shivammathur/extensions/phpredis@8.4'], (new redis(M::mock(PHPInfo::class), $macOS))->getSystemPackageNames(new Package('php@8.4', '8.4.14_1', 'php@8.4')));
        self::assertNull((new redis(M::mock(PHPInfo::class), M::mock(OS\Debian::class)))->getSystemPackageNames(new Package('php8.4-cli', '8.4.14-1', 'php8.4')));
    }
}
