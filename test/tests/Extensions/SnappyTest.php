<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\snappy;
use IPE\Filesystem;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemPackager\Homebrew;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class SnappyTest extends TestCase
{
    /**
     * @return array<array{class-string<\IPE\OS>, int, string}> the OS, its version, and the expected version
     */
    public static function provideDefaultVersions()
    {
        return [
            [OS\Debian::class, OS\Debian::STRETCH, snappy::VERSION],
            [OS\Debian::class, OS\Debian::BUSTER, '0.2.1'],
            [OS\Debian::class, OS\Debian::BULLSEYE, snappy::VERSION],
            [OS\Alpine::class, 310, '0.2.1'],
            [OS\Alpine::class, 311, '0.2.1'],
            [OS\Alpine::class, 312, snappy::VERSION],
        ];
    }

    /**
     * @dataProvider provideDefaultVersions
     *
     * @param class-string<\IPE\OS> $osClass
     * @param int $osVersion
     * @param string $expected
     *
     * @return void
     */
    public function testDefaultVersion($osClass, $osVersion, $expected)
    {
        $php = M::mock(PHPInfo::class);
        $os = M::mock($osClass);
        self::assertInstanceOf(OS::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        self::assertSame($expected, (new snappy($php, $os))->getVendorArchiveVersion('', new Values()));
    }

    /**
     * @return void
     */
    public function testConfigureArguments()
    {
        $php = M::mock(PHPInfo::class);
        self::assertSame(['--with-snappy-includedir=/usr'], (new snappy($php, M::mock(OS\Debian::class)))->getConfigureArguments(self::createBuildContext()));
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->once()->andReturn([0, "/opt/homebrew\n"]);
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getSystemPackager')->andReturn(new Homebrew($os));
        self::assertSame(['--with-snappy-includedir=/opt/homebrew/opt/snappy'], (new snappy($php, $os))->getConfigureArguments(self::createBuildContext()));
    }
}
