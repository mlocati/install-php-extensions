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
     * @return array<array{int, string}> the PHP version, and the expected preprocessor flags
     */
    public static function providePreprocessorFlags()
    {
        return [
            [505, '-include inttypes.h'],
            [506, ''],
            [806, ''],
        ];
    }

    /**
     * @dataProvider providePreprocessorFlags
     *
     * @param int $phpVersion
     * @param string $expected
     *
     * @return void
     */
    public function testPreprocessorFlags($phpVersion, $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
        $os = M::mock(OS::class);
        self::assertSame($expected, (new amqp($php, $os))->getPreprocessorFlags(self::createBuildContext()));
    }
}
