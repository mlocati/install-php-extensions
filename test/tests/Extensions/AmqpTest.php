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
     * @return array<array{int, string}> the PHP version, and the expected compiler flags
     */
    public static function provideCompilerFlags()
    {
        return [
            [505, '-include inttypes.h'],
            [506, ''],
            [806, ''],
        ];
    }

    /**
     * @dataProvider provideCompilerFlags
     *
     * @param int $phpVersion
     * @param string $expected
     *
     * @return void
     */
    public function testCompilerFlags($phpVersion, $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
        $os = M::mock(OS::class);
        self::assertSame($expected, (new amqp($php, $os))->getCompilerFlags(self::createBuildContext()));
    }
}
