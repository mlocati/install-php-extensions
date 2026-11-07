<?php

namespace IPE\Test\Extensions;

use IPE\Exception;
use IPE\Extensions\ioncube_loader;
use IPE\Filesystem;
use IPE\Filesystem\Downloader;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class IoncubeLoaderTest extends TestCase
{
    /**
     * @param int $version
     * @param bool $threadSafe
     * @param int $bits
     *
     * @return \IPE\PHPInfo
     */
    private static function createPHP($version = 804, $threadSafe = false, $bits = 64)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($version);
        $php->shouldReceive('isThreadSafe')->andReturn($threadSafe);
        $php->shouldReceive('getBits')->andReturn($bits);

        return $php;
    }

    /**
     * @param int $version
     *
     * @return \IPE\OS&\Mockery\MockInterface
     */
    private static function createAlpine($version)
    {
        $os = M::mock(OS\Alpine::class);
        $os->shouldReceive('getNumericVersion')->andReturn($version);
        $os->shouldReceive('getMachineArchitecture')->andReturn(OS\Architecture::X64);

        return $os;
    }

    /**
     * @return \IPE\Extensions\ioncube_loader
     */
    private static function createExtension(PHPInfo $php, OS $os)
    {
        return new ioncube_loader($php, $os, M::mock(Downloader::class));
    }

    /**
     * @param non-empty-string $expected the only file that exists
     *
     * @return \IPE\Filesystem
     */
    private static function createFilesystem($expected)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->andReturnUsing(static function ($path) use ($expected) {
            return $path === $expected;
        });

        return $fs;
    }

    /**
     * @return void
     */
    public function testDefaultVersion()
    {
        self::assertSame('14.0.0', self::createExtension(self::createPHP(), self::createAlpine(312))->getDefaultVersionRequirements());
        self::assertSame('', self::createExtension(self::createPHP(), self::createAlpine(313))->getDefaultVersionRequirements());
        self::assertSame('', self::createExtension(self::createPHP(), M::mock(OS\Debian::class))->getDefaultVersionRequirements());
    }

    /**
     * @return void
     */
    public function testArchiveURL()
    {
        $php = self::createPHP();
        $debian = M::mock(OS\Debian::class);
        $debian->shouldReceive('getMachineArchitecture')->andReturn(OS\Architecture::ARM64);
        self::assertStringEndsWith('/ioncube_loaders_lin_aarch64.tar.gz', self::createExtension($php, $debian)->getBinaryArchiveURL(''));
        $alpine = self::createAlpine(323);
        self::assertStringEndsWith('/ioncube_loaders_lin-musl_x86-64.tar.gz', self::createExtension($php, $alpine)->getBinaryArchiveURL(''));
        self::assertStringEndsWith('/ioncube_loaders_lin_x86-64_14.0.0.tar.gz', self::createExtension($php, $alpine)->getBinaryArchiveURL('14.0.0'));
        self::assertStringEndsWith('/ioncube_loaders_lin-musl_x86-64_14.4.0.tar.gz', self::createExtension($php, $alpine)->getBinaryArchiveURL('14.4.0'));
    }

    /**
     * @return array<array{int, string, string}>
     */
    public static function provideVendorArchitectures()
    {
        return [
            [32, OS\Architecture::X86, 'x86'],
            [32, OS\Architecture::X64, 'x86'],
            [32, OS\Architecture::ARM32, 'armv7l'],
            [32, OS\Architecture::ARM64, 'armv7l'],
            [64, OS\Architecture::X64, 'x86-64'],
            [64, OS\Architecture::ARM64, 'aarch64'],
        ];
    }

    /**
     * @dataProvider provideVendorArchitectures
     *
     * @param int $bits
     * @param string $architecture
     * @param string $expected
     *
     * @return void
     */
    public function testVendorArchitectures($bits, $architecture, $expected)
    {
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getMachineArchitecture')->andReturn($architecture);
        self::assertStringEndsWith("/ioncube_loaders_lin_{$expected}.tar.gz", self::createExtension(self::createPHP(804, false, $bits), $os)->getBinaryArchiveURL(''));
    }

    /**
     * @return void
     */
    public function testOtherArchitecturesAreLeftToTheVendor()
    {
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getMachineArchitecture')->andReturn(OS\Architecture::OTHER);
        $os->shouldReceive('getRawMachineArchitecture')->andReturn('riscv64');
        self::assertStringEndsWith('_lin_riscv64.tar.gz', self::createExtension(self::createPHP(), $os)->getBinaryArchiveURL(''));
    }

    /**
     * @return array<array{bool, int, bool, string, string}>
     */
    public static function provideModules()
    {
        return [
            [false, 804, false, '', 'ioncube_loader_lin_8.4.so'],
            [false, 700, true, '', 'ioncube_loader_lin_7.0_ts.so'],
            [true, 804, false, '', 'ioncube_loader_lin-musl_8.4.so'],
            [true, 804, false, '14.0.0', 'ioncube_loader_lin_8.4.so'],
        ];
    }

    /**
     * @dataProvider provideModules
     *
     * @param bool $alpine
     * @param int $phpVersion
     * @param bool $threadSafe
     * @param string $version
     * @param non-empty-string $expected
     *
     * @return void
     */
    public function testFindModule($alpine, $phpVersion, $threadSafe, $version, $expected)
    {
        $php = self::createPHP($phpVersion, $threadSafe);
        foreach (["/src/{$expected}" => "/src/{$expected}", '/src/other.so' => ''] as $existingFile => $expectedResult) {
            $os = $alpine ? self::createAlpine(323) : M::mock(OS\Debian::class);
            $os->shouldReceive('getFilesystem')->andReturn(self::createFilesystem($existingFile));
            $extension = self::createExtension($php, $os);
            self::assertSame($expectedResult, $extension->findBinaryModule('/src', $version));
            self::assertTrue($extension->isZendExtension());
        }
    }

    /**
     * @return void
     */
    public function testInexactVersion()
    {
        $this->expectException(Exception\UnsupportedExtension::class);
        self::createExtension(self::createPHP(), M::mock(OS\Debian::class))->getBinaryArchiveURL('^14');
    }

    /**
     * @return void
     */
    public function testUnsupportedOS()
    {
        $os = M::mock(OS\Posix::class);
        $os->shouldReceive('getDisplayName')->andReturn('The OS');
        $os->shouldReceive('getFilesystem')->andReturn(M::mock(Filesystem::class)->shouldIgnoreMissing());
        $this->expectException(Exception\UnsupportedOS::class);
        self::createExtension(self::createPHP(), $os)->findBinaryModule('/src', '');
    }
}
