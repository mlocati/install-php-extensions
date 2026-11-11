<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\openswoole;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class OpenswooleTest extends TestCase
{
    /**
     * @return array<array{string, array<string, string>}> the version of the extension, and the expected configure environment
     */
    public static function provideConfigureEnvironment()
    {
        return [
            ['4.10.0', ['CXXFLAGS' => '-include cstdint']],
            ['22.0.0', ['CXXFLAGS' => '-include cstdint']],
            ['22.1.0', []],
            ['', []],
        ];
    }

    /**
     * @dataProvider provideConfigureEnvironment
     *
     * @param string $version
     * @param array<string, string> $expected
     *
     * @return void
     */
    public function testConfigureEnvironment($version, array $expected)
    {
        self::assertSame($expected, (new openswoole(M::mock(PHPInfo::class), M::mock(OS::class)))->getConfigureEnvironment(self::createBuildContext($version)));
    }
}
