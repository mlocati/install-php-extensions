<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\tensor;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class TensorTest extends TestCase
{
    /**
     * @return array<array{string, string}> the PHP version, and the expected version requirements
     */
    public static function provideDefaultVersions()
    {
        return [
            ['7.3.33', '2.2.3'],
            ['7.4.33', '3.0.5'],
            ['8.0.7', '3.0.5'],
            ['8.0.9', '3.0.5'],
            ['8.0.10', ''],
            ['8.0.30', ''],
            ['8.1.0', ''],
        ];
    }

    /**
     * @dataProvider provideDefaultVersions
     *
     * @param string $phpVersion
     * @param string $expected
     *
     * @return void
     */
    public function testDefaultVersion($phpVersion, $expected)
    {
        list($major, $minor) = explode('.', $phpVersion);
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn((int) $major * 100 + (int) $minor);
        $php->shouldReceive('getBaseVersion')->andReturn($phpVersion);
        self::assertSame($expected, (new tensor($php, M::mock(OS::class)))->getDefaultVersionRequirements());
    }

    /**
     * @return array<array{class-string<\IPE\OS>, int, bool}> the OS, its version, and whether the OpenBLAS headers must be included explicitly
     */
    public static function provideConfigureEnvironment()
    {
        return [
            [OS\Debian::class, OS\Debian::STRETCH, true],
            [OS\Debian::class, OS\Debian::BUSTER, false],
            [OS\Alpine::class, 312, false],
        ];
    }

    /**
     * @dataProvider provideConfigureEnvironment
     *
     * @param class-string<\IPE\OS> $osClass
     * @param int $osVersion
     * @param bool $openBLAS
     *
     * @return void
     */
    public function testConfigureEnvironment($osClass, $osVersion, $openBLAS)
    {
        $os = M::mock($osClass);
        self::assertInstanceOf(OS::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        $expected = $openBLAS ? ['CPPFLAGS' => '-I/usr/include/openblas'] : [];
        self::assertSame($expected, (new tensor(M::mock(PHPInfo::class), $os))->getConfigureEnvironment(self::createBuildContext()));
    }
}
