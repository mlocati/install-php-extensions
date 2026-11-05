<?php

namespace IPE\Test\OS;

use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ArchitectureTest extends TestCase
{
    /**
     * @return array<array{int, string, string, string, string, string}>
     */
    public static function provideCases()
    {
        return [
            [64, OS\Architecture::X64, 'x86_64', OS\Architecture::X64, 'x64-name', 'x86_64'],
            [32, OS\Architecture::X64, 'x86_64', OS\Architecture::X86, 'i386', '32-bit PHP on x86_64'],
            [32, OS\Architecture::X86, 'i686', OS\Architecture::X86, 'i386', '32-bit PHP on i686'],
            [32, OS\Architecture::ARM64, 'aarch64', OS\Architecture::ARM32, 'armv7l', '32-bit PHP on aarch64'],
            [64, OS\Architecture::OTHER, 'riscv64', OS\Architecture::OTHER, 'riscv64', 'riscv64'],
        ];
    }

    /**
     * @dataProvider provideCases
     *
     * @param int $bits
     * @param string $machine
     * @param string $raw
     * @param string $expectedArchitecture
     * @param string $expectedVendorName
     * @param string $expectedDescription
     *
     * @return void
     */
    public function testArchitecture($bits, $machine, $raw, $expectedArchitecture, $expectedVendorName, $expectedDescription)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getBits')->andReturn($bits);
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getMachineArchitecture')->andReturn($machine);
        $os->shouldReceive('getRawMachineArchitecture')->andReturn($raw);
        self::assertSame($expectedArchitecture, OS\Architecture::ofPHP($php, $os));
        self::assertSame($expectedVendorName, OS\Architecture::getVendorName($php, $os, [OS\Architecture::X64 => 'x64-name']));
        self::assertSame($expectedDescription, OS\Architecture::describe($php, $os));
    }

    /**
     * @return void
     */
    public function testVendorNameWins()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getBits')->andReturn(32);
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getMachineArchitecture')->andReturn(OS\Architecture::X64);
        self::assertSame('x86', OS\Architecture::getVendorName($php, $os, [OS\Architecture::X86 => 'x86']));
    }
}
