<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\v8js;
use IPE\Filesystem;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemPackager\Homebrew;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class V8jsTest extends TestCase
{
    /**
     * @return void
     */
    public function testConfigureArguments()
    {
        $php = M::mock(PHPInfo::class);
        self::assertSame([], (new v8js($php, M::mock(OS\Debian::class)))->getConfigureArguments(self::createBuildContext()));
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->once()->andReturn([0, "/opt/homebrew\n"]);
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getSystemPackager')->andReturn(new Homebrew($os));
        self::assertSame(['--with-v8js=/opt/homebrew/opt/v8'], (new v8js($php, $os))->getConfigureArguments(self::createBuildContext()));
    }
}
