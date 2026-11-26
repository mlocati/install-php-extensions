<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\zookeeper;
use IPE\Filesystem;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemPackager\Homebrew;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ZookeeperTest extends TestCase
{
    /**
     * @return void
     */
    public function testConfigureArguments()
    {
        $php = M::mock(PHPInfo::class);
        self::assertSame([], (new zookeeper($php, M::mock(OS\Debian::class)))->getConfigureArguments(self::createBuildContext()));
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->once()->andReturn([0, "/opt/homebrew\n"]);
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getSystemPackager')->andReturn(new Homebrew($os));
        self::assertSame(['--with-libzookeeper-dir=/opt/homebrew/opt/zookeeper'], (new zookeeper($php, $os))->getConfigureArguments(self::createBuildContext()));
    }
}
