<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\event;
use IPE\Filesystem;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemPackager\Homebrew;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class EventTest extends TestCase
{
    /**
     * @return void
     */
    public function testConfigureArguments()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getSSLLibrary')->andReturn(null);
        $php->shouldReceive('getInstalledExtensions')->andReturn([]);
        $debian = M::mock(OS\Debian::class);
        $debian->shouldReceive('getNumericVersion')->andReturn(OS\Debian::TRIXIE);
        self::assertSame(
            ['--with-event-libevent-dir=/usr', '--with-event-pthreads', '--with-event-extra', '--with-event-openssl', '--disable-event-sockets'],
            (new event($php, $debian))->getConfigureArguments(self::createBuildContext('3.2.0'))
        );
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->once()->andReturn([0, "/opt/homebrew\n"]);
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getSystemPackager')->andReturn(new Homebrew($os));
        self::assertSame(
            ['--with-event-libevent-dir=/opt/homebrew/opt/libevent', '--with-event-pthreads', '--with-event-extra', '--with-event-openssl', '--disable-event-sockets'],
            (new event($php, $os))->getConfigureArguments(self::createBuildContext('3.2.0'))
        );
    }
}
