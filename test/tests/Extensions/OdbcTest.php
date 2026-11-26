<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\odbc;
use IPE\Filesystem;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemPackager\Homebrew;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class OdbcTest extends TestCase
{
    /**
     * @return void
     */
    public function testConfigureArguments()
    {
        $php = M::mock(PHPInfo::class);
        self::assertSame(['--with-unixODBC=shared,/usr'], (new odbc($php, M::mock(OS\Debian::class)))->getConfigureArguments(self::createBuildContext()));
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->once()->andReturn([0, "/opt/homebrew\n"]);
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getSystemPackager')->andReturn(new Homebrew($os));
        self::assertSame(['--with-unixODBC=shared,/opt/homebrew/opt/unixodbc'], (new odbc($php, $os))->getConfigureArguments(self::createBuildContext()));
    }
}
