<?php

namespace IPE\Test;

use IPE\Architecture;
use IPE\OS;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class PHPInfoArchitectureTest extends TestCase
{
    /**
     * @return array<array{int, string, non-empty-string, string, string, string|null, string}>
     */
    public static function provideCases()
    {
        return [
            [64, '', 'x86_64', Architecture::X64, 'x86_64', 'x64-name', 'x86_64'],
            [32, '', 'x86_64', Architecture::X86, 'i386', null, '32-bit PHP on x86_64'],
            [32, '', 'i686', Architecture::X86, 'i686', null, '32-bit PHP on i686'],
            [32, '', 'aarch64', Architecture::ARM32, 'armv7l', null, '32-bit PHP on aarch64'],
            [64, '', 'riscv64', '', 'riscv64', null, 'riscv64'],
            [64, 'x64', 'AMD64', Architecture::X64, 'x64', 'x64-name', 'AMD64'],
            [32, 'x86', 'AMD64', Architecture::X86, 'x86', null, '32-bit PHP on AMD64'],
            [64, 'x64', 'ARM64', Architecture::X64, 'x64', 'x64-name', 'x64 PHP on ARM64'],
            [64, 'arm64', 'ARM64', Architecture::ARM64, 'arm64', null, 'ARM64'],
            [64, 'unknown', 'x86_64', Architecture::X64, 'x86_64', 'x64-name', 'x86_64'],
        ];
    }

    /**
     * @dataProvider provideCases
     *
     * @param int $bits
     * @param string $buildArchitecture the architecture reported by phpinfo()
     * @param non-empty-string $machineRaw
     * @param string $expectedName
     * @param string $expectedRawName
     * @param string|null $expectedMapped
     * @param string $expectedDescription
     *
     * @return void
     */
    public function testArchitecture($bits, $buildArchitecture, $machineRaw, $expectedName, $expectedRawName, $expectedMapped, $expectedDescription)
    {
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getArchitecture')->andReturn(new Architecture($machineRaw));
        $php = self::mockPHPInfo($os, $bits, $buildArchitecture);
        $architecture = $php->getArchitecture();
        self::assertSame($architecture, $php->getArchitecture());
        self::assertSame($expectedName, $architecture->getName());
        self::assertSame($expectedRawName, $architecture->getRawName());
        self::assertSame($expectedMapped, $architecture->map([Architecture::X64 => 'x64-name']));
        self::assertSame($expectedMapped === null ? $expectedRawName : $expectedMapped, $architecture->map([Architecture::X64 => 'x64-name'], true));
        self::assertSame($expectedDescription, $php->describeArchitecture());
    }
}
