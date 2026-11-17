<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\opcache;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class OpcacheTest extends TestCase
{
    /**
     * @return array<array{int, list<string>}>
     */
    public static function provideSystemDependencies()
    {
        return [
            [800, []],
            [801, [SystemDependencies\libcapstone::HANDLE, SystemDependencies\libgcc::HANDLE]],
        ];
    }

    /**
     * @dataProvider provideSystemDependencies
     *
     * @param int $phpVersion
     * @param list<string> $expected
     *
     * @return void
     */
    public function testSystemDependencies($phpVersion, array $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
        $extension = new opcache($php, M::mock(OS\Alpine::class));
        self::assertSame($expected, $extension->getRequiredSystemDependencies('', new Values()));
    }
}
