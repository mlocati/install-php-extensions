<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\pdo_firebird;
use IPE\Filesystem;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemPackager\Homebrew;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class PdoFirebirdTest extends TestCase
{
    /**
     * @return array<array{int, list<string>}>
     */
    public static function provideConfigureArgumentsCases()
    {
        return [
            [803, ['--with-pdo-firebird=/opt/homebrew/opt/firebird-client@3']],
            [804, ['--with-pdo-firebird=/opt/homebrew/opt/firebird-client']],
        ];
    }

    /**
     * @dataProvider provideConfigureArgumentsCases
     *
     * @param int $phpVersion
     * @param list<string> $expected
     *
     * @return void
     */
    public function testConfigureArguments($phpVersion, array $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
        self::assertSame([], (new pdo_firebird($php, M::mock(OS\Debian::class)))->getConfigureArguments(self::createBuildContext()));
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->once()->andReturn([0, "/opt/homebrew\n"]);
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getSystemPackager')->andReturn(new Homebrew($os));
        self::assertSame($expected, (new pdo_firebird($php, $os))->getConfigureArguments(self::createBuildContext()));
    }
}
