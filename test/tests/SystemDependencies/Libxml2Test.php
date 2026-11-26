<?php

namespace IPE\Test\SystemDependencies;

use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\libxml2;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class Libxml2Test extends TestCase
{
    /**
     * @return void
     */
    public function testBuildEnvironment()
    {
        $php = M::mock(PHPInfo::class);
        self::assertSame([], (new libxml2($php, M::mock(OS\Debian::class), new Values()))->getBuildEnvironment());
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getSDKPath')->andReturn('/Library/Developer/CommandLineTools/SDKs/MacOSX.sdk');
        self::assertSame(
            ['LIBXML_CFLAGS' => '-I/Library/Developer/CommandLineTools/SDKs/MacOSX.sdk/usr/include/libxml2', 'LIBXML_LIBS' => '-lxml2'],
            (new libxml2($php, $os, new Values()))->getBuildEnvironment()
        );
    }
}
