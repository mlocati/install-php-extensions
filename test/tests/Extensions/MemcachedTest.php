<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\memcached;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class MemcachedTest extends TestCase
{
    /**
     * @return array<array{string, list<string>}>
     */
    public static function provideMacOSConfigureArguments()
    {
        return [
            ['/sdk', ['--enable-memcached-json', '--with-zlib-dir=/sdk/usr', '--enable-memcached-msgpack']],
            ['', ['--enable-memcached-json', '--enable-memcached-msgpack']],
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
        $php->shouldReceive('getInstalledExtensions')->andReturn(['msgpack']);
        $context = self::createBuildContext('3.4.0');
        self::assertSame(['--enable-memcached-json', '--enable-memcached-msgpack'], (new memcached($php, M::mock(OS\Debian::class)))->getConfigureArguments($context));
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getSDKPath')->andReturn($sdkPath);
        self::assertSame($expected, (new memcached($php, $os))->getConfigureArguments($context));
    }
}
