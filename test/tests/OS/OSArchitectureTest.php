<?php

namespace IPE\Test\OS;

use IPE\Architecture;
use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\Service\Container;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class OSArchitectureTest extends TestCase
{
    /**
     * @return array<array{string, string}>
     */
    public static function provideArchitectures()
    {
        return [
            ['i386', Architecture::X86],
            ['i486', Architecture::X86],
            ['i586', Architecture::X86],
            ['i686', Architecture::X86],
            ['x86', Architecture::X86],
            ['x86_64', Architecture::X64],
            ['amd64', Architecture::X64],
            ['AMD64', Architecture::X64],
            ['x64', Architecture::X64],
            ['ARM64', Architecture::ARM64],
            ['aarch64', Architecture::ARM64],
            ['arm64', Architecture::ARM64],
            ['armv8', Architecture::ARM64],
            ['arm', Architecture::ARM32],
            ['armv5l', Architecture::ARM32],
            ['armv6l', Architecture::ARM32],
            ['armv7l', Architecture::ARM32],
            ['armv8l', Architecture::ARM32],
            ['riscv64', ''],
            ['ppc64le', ''],
            ['s390x', ''],
        ];
    }

    /**
     * @dataProvider provideArchitectures
     *
     * @param string $raw
     * @param string $expected
     *
     * @return void
     */
    public function testDetected($raw, $expected)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('uname', ['-m'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT)))->andReturn([0, "{$raw}\n"]);
        $os = new OS\Alpine('3.23.0', $fs, new Container());
        $architecture = $os->getArchitecture();
        self::assertSame($architecture, $os->getArchitecture(), 'The command should be executed only once');
        self::assertSame($expected, $architecture->getName());
        self::assertSame($raw, $architecture->getRawName());
    }

    /**
     * @return array<array{array<string, string>, string, string}>
     */
    public static function provideWindowsArchitectures()
    {
        return [
            [['PROCESSOR_ARCHITECTURE' => 'AMD64'], 'AMD64', Architecture::X64],
            [['PROCESSOR_ARCHITECTURE' => 'x86'], 'x86', Architecture::X86],
            [['PROCESSOR_ARCHITECTURE' => 'ARM64'], 'ARM64', Architecture::ARM64],
            [['PROCESSOR_ARCHITECTURE' => 'ARM'], 'ARM', Architecture::ARM32],
            [['PROCESSOR_ARCHITECTURE' => 'x86', 'PROCESSOR_ARCHITEW6432' => 'AMD64'], 'AMD64', Architecture::X64],
            [['PROCESSOR_ARCHITECTURE' => 'IA64'], 'IA64', ''],
        ];
    }

    /**
     * @dataProvider provideWindowsArchitectures
     *
     * @param array<string, string> $environment
     * @param string $raw
     * @param string $expected
     *
     * @return void
     */
    public function testWindows(array $environment, $raw, $expected)
    {
        $originals = [];
        foreach (['PROCESSOR_ARCHITECTURE', 'PROCESSOR_ARCHITEW6432'] as $name) {
            $originals[$name] = getenv($name);
            putenv(isset($environment[$name]) ? "{$name}={$environment[$name]}" : $name);
        }
        try {
            $os = new OS\Windows('Microsoft Windows 11 Pro', 64, '10.0.26200', M::mock(Filesystem::class));
            self::assertSame($raw, $os->getArchitecture()->getRawName());
            self::assertSame($expected, $os->getArchitecture()->getName());
        } finally {
            foreach ($originals as $name => $original) {
                putenv($original === false ? $name : "{$name}={$original}");
            }
        }
    }

    /**
     * @return void
     */
    public function testFailure()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('run')->andReturn([1, '']);
        $this->expectException(Exception\IO::class);
        (new OS\Alpine('3.23.0', $fs, new Container()))->getArchitecture();
    }
}
