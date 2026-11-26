<?php

namespace IPE\Test\Extensions;

use IPE\Extensions;
use IPE\Filesystem;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemPackager\Homebrew;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class BundledOnMacOSTest extends TestCase
{
    /**
     * @return list<array{class-string<\IPE\Extension&\IPE\Extension\ProvidesConfigureArguments>, int, list<string>}>
     */
    public static function provideConfigureArguments()
    {
        $opt = '/opt/homebrew/opt';
        $gd73 = ['--with-gd', "--with-jpeg-dir={$opt}/jpeg-turbo", "--with-png-dir={$opt}/libpng", '--with-zlib-dir=/sdk/usr', "--with-xpm-dir={$opt}/libxpm", "--with-freetype-dir={$opt}/freetype"];

        return [
            [Extensions\bz2::class, 805, ['--with-bz2=/sdk/usr']],
            [Extensions\dba::class, 701, ["--with-db4={$opt}/berkeley-db@5", "--with-tcadb={$opt}/tokyo-cabinet", "--with-gdbm={$opt}/gdbm"]],
            [Extensions\dba::class, 805, ["--with-db4={$opt}/berkeley-db@5", "--with-lmdb={$opt}/lmdb", "--with-tcadb={$opt}/tokyo-cabinet", "--with-gdbm={$opt}/gdbm"]],
            [Extensions\gd::class, 506, array_merge($gd73, ['--enable-gd-native-ttf', "--with-vpx-dir={$opt}/libvpx"])],
            [Extensions\gd::class, 703, array_merge($gd73, ["--with-webp-dir={$opt}/webp"])],
            [Extensions\gettext::class, 805, ["--with-gettext={$opt}/gettext"]],
            [Extensions\gmp::class, 805, ["--with-gmp={$opt}/gmp"]],
        ];
    }

    /**
     * @dataProvider provideConfigureArguments
     *
     * @param class-string<\IPE\Extension&\IPE\Extension\ProvidesConfigureArguments> $class
     * @param int $phpVersion
     * @param list<string> $expected
     *
     * @return void
     */
    public function testConfigureArguments($class, $phpVersion, array $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->andReturn([0, "/opt/homebrew\n"]);
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getSystemPackager')->andReturn(new Homebrew($os));
        $os->shouldReceive('getSDKPath')->andReturn('/sdk');
        $extension = new $class($php, $os);
        self::assertSame($expected, $extension->getConfigureArguments(self::createBuildContext()));
    }

    /**
     * @return void
     */
    public function testDBAUsesGDBM()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(805);
        $dba = new Extensions\dba($php, M::mock(OS\MacOS::class));
        self::assertSame(['lib-db', 'lib-lmdb', 'lib-tokyocabinet', 'lib-gdbm'], $dba->getRequiredSystemDependencies('', new \IPE\Option\Values()));
    }
}
