<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\yar;
use IPE\Filesystem;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemPackager\Homebrew;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class YarTest extends TestCase
{
    /**
     * @return void
     */
    public function testConfigureArguments()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getInstalledExtensions')->andReturn(['msgpack']);
        $debian = M::mock(OS\Debian::class);
        self::assertSame([], (new yar($php, $debian))->getConfigureArguments(self::createBuildContext('1.2.3')));
        self::assertSame(['--enable-msgpack'], (new yar($php, $debian))->getConfigureArguments(self::createBuildContext('1.2.4')));
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->once()->andReturn([0, "/opt/homebrew\n"]);
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getSystemPackager')->andReturn(new Homebrew($os));
        self::assertSame(['--with-curl=/opt/homebrew/opt/curl', '--enable-msgpack'], (new yar($php, $os))->getConfigureArguments(self::createBuildContext()));
    }
}
