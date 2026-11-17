<?php

namespace IPE\Test\SystemDependencies;

use IPE\Filesystem;
use IPE\Installer\State;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\libcurl;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class LibcurlTest extends TestCase
{
    /**
     * @return void
     */
    public function testHeadersLinkedWithGcc()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('exists')->with('/usr/include/curl')->andReturn(false);
        $fs->shouldReceive('run')->once()->andReturn([0, "x86_64-linux-gnu\n"]);
        $fs->shouldReceive('createSymlink')->once()->with('/usr/include/x86_64-linux-gnu/curl', '/usr/include/curl');
        $fs->shouldReceive('deleteFile')->once()->with('/usr/include/curl');
        $state = new State(M::mock(PHPInfo::class), $fs);
        self::createDependency($fs)->install($state);
        $state->shutdown(new \IPE\Logger());
    }

    /**
     * @return void
     */
    public function testHeadersNotLinkedWithoutGcc()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('exists')->with('/usr/include/curl')->andReturn(false);
        $fs->shouldReceive('run')->once()->andReturn([127, '']);
        $fs->shouldNotReceive('createSymlink');
        $fs->shouldNotReceive('deleteFile');
        $state = new State(M::mock(PHPInfo::class), $fs);
        self::createDependency($fs)->install($state);
        $state->shutdown(new \IPE\Logger());
    }

    /**
     * @return \IPE\SystemDependencies\libcurl
     */
    private static function createDependency(Filesystem $fs)
    {
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(703);

        return new libcurl($php, $os, new Values());
    }
}
