<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\openswoole;
use IPE\Filesystem;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies;
use IPE\SystemPackager\Homebrew;
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

    /**
     * @return array<array{class-string<\IPE\OS>, int, bool}>
     */
    public static function provideBrotli()
    {
        return [
            [OS\Alpine::class, 311, false],
            [OS\Alpine::class, 312, true],
            [OS\Debian::class, OS\Debian::STRETCH, false],
            [OS\Debian::class, OS\Debian::BUSTER, true],
        ];
    }

    /**
     * @dataProvider provideBrotli
     *
     * @param class-string<\IPE\OS> $osClass
     * @param int $osVersion
     * @param bool $expected
     *
     * @return void
     */
    public function testBrotliOnlyWhenProvided($osClass, $osVersion, $expected)
    {
        $os = M::mock($osClass);
        self::assertInstanceOf(OS::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        $dependencies = (new openswoole(M::mock(PHPInfo::class), $os))->getRequiredSystemDependencies('4.10.0', new Values());
        self::assertSame($expected, in_array(SystemDependencies\libbrotli::HANDLE, $dependencies, true));
    }

    /**
     * @return void
     */
    public function testMacOS()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getInstalledExtensions')->andReturn([]);
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->once()->andReturn([0, "/opt/homebrew\n"]);
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getSystemPackager')->andReturn(new Homebrew($os));
        $extension = new openswoole($php, $os);
        self::assertNotContains(SystemDependencies\linuxheaders::HANDLE, $extension->getRequiredSystemDependencies('25.2.0', new Values()));
        self::assertSame(
            ['--enable-openssl', '--enable-http2', '--enable-mysqlnd', '--with-postgres=/opt/homebrew/opt/libpq', '--enable-hook-curl'],
            $extension->getConfigureArguments(self::createBuildContext('25.2.0'))
        );
    }
}
