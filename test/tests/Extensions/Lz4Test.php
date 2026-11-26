<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\lz4;
use IPE\Filesystem;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemPackager\Homebrew;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class Lz4Test extends TestCase
{
    /**
     * @return array<array{class-string<\IPE\OS>, int, int, string}> the OS, its version, the PHP version, and the expected version
     */
    public static function provideDefaultVersions()
    {
        return [
            [OS\Debian::class, OS\Debian::STRETCH, 704, '0.4.4'],
            [OS\Debian::class, OS\Debian::BOOKWORM, 704, lz4::VERSION],
            [OS\Debian::class, OS\Debian::BOOKWORM, 800, '0.4.4'],
            [OS\Debian::class, OS\Debian::BOOKWORM, 801, lz4::VERSION],
            [OS\Ubuntu::class, OS\Ubuntu::BIONIC, 801, '0.4.4'],
            [OS\Ubuntu::class, OS\Ubuntu::FOCAL, 801, lz4::VERSION],
            [OS\Alpine::class, 313, 800, '0.4.4'],
            [OS\Alpine::class, 313, 806, lz4::VERSION],
        ];
    }

    /**
     * @dataProvider provideDefaultVersions
     *
     * @param class-string<\IPE\OS> $osClass
     * @param int $osVersion
     * @param int $phpVersion
     * @param string $expected
     *
     * @return void
     */
    public function testDefaultVersion($osClass, $osVersion, $phpVersion, $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
        $os = M::mock($osClass);
        self::assertInstanceOf(OS::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        self::assertSame($expected, (new lz4($php, $os))->getVendorArchiveVersion('', new Values()));
    }

    /**
     * @return void
     */
    public function testConfigureArguments()
    {
        $php = M::mock(PHPInfo::class);
        self::assertSame(['--with-lz4-includedir=/usr'], (new lz4($php, M::mock(OS\Debian::class)))->getConfigureArguments(self::createBuildContext()));
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->once()->andReturn([0, "/opt/homebrew\n"]);
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getSystemPackager')->andReturn(new Homebrew($os));
        self::assertSame(['--with-lz4-includedir=/opt/homebrew/opt/lz4'], (new lz4($php, $os))->getConfigureArguments(self::createBuildContext()));
    }
}
