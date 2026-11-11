<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\amqp;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class AmqpTest extends TestCase
{
    /**
     * @return array<array{int, array<string, string>}> the PHP version, and the expected configure environment
     */
    public static function provideConfigureEnvironment()
    {
        return [
            [505, ['CPPFLAGS' => '-include inttypes.h']],
            [506, []],
            [806, []],
        ];
    }

    /**
     * @dataProvider provideConfigureEnvironment
     *
     * @param int $phpVersion
     * @param array<string, string> $expected
     *
     * @return void
     */
    public function testConfigureEnvironment($phpVersion, array $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
        $os = M::mock(OS::class);
        self::assertSame($expected, (new amqp($php, $os))->getConfigureEnvironment(self::createBuildContext()));
    }
}
