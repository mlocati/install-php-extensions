<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\snappy;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class SnappyTest extends TestCase
{
    /**
     * @return array<array{class-string<\IPE\OS>, int, string}> the OS, its version, and the expected version
     */
    public static function provideDefaultVersions()
    {
        return [
            [OS\Debian::class, OS\Debian::VERSION_STRETCH, snappy::VERSION],
            [OS\Debian::class, OS\Debian::VERSION_BUSTER, '0.2.1'],
            [OS\Debian::class, OS\Debian::VERSION_BULLSEYE, snappy::VERSION],
            [OS\Alpine::class, 310, '0.2.1'],
            [OS\Alpine::class, 311, '0.2.1'],
            [OS\Alpine::class, 312, snappy::VERSION],
        ];
    }

    /**
     * @dataProvider provideDefaultVersions
     *
     * @param class-string<\IPE\OS> $osClass
     * @param int $osVersion
     * @param string $expected
     *
     * @return void
     */
    public function testDefaultVersion($osClass, $osVersion, $expected)
    {
        $php = M::mock(PHPInfo::class);
        $os = M::mock($osClass);
        self::assertInstanceOf(OS::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        self::assertSame($expected, (new snappy($php, $os))->getVendorArchiveVersion('', new Values()));
    }
}
