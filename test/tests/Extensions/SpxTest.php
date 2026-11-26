<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\spx;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class SpxTest extends TestCase
{
    /**
     * @return array<array{string, list<string>}>
     */
    public static function provideMacOSConfigureArguments()
    {
        return [
            ['/sdk', ['--with-zlib-dir=/sdk/usr']],
            ['', []],
        ];
    }

    /**
     * @dataProvider provideMacOSConfigureArguments
     *
     * @param string $sdkPath
     * @param list<string> $expected
     *
     * @return void
     */
    public function testConfigureArguments($sdkPath, array $expected)
    {
        $php = M::mock(PHPInfo::class);
        self::assertSame([], (new spx($php, M::mock(OS\Debian::class)))->getConfigureArguments(self::createBuildContext()));
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getSDKPath')->andReturn($sdkPath);
        self::assertSame($expected, (new spx($php, $os))->getConfigureArguments(self::createBuildContext()));
    }
}
