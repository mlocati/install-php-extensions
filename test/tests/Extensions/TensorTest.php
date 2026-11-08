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
     * @return array<array{class-string<\IPE\OS>, int, bool}> the OS, its version, and whether the OpenBLAS headers must be included explicitly
     */
    public static function provideCompilerFlags()
    {
        return [
            [OS\Debian::class, OS\Debian::VERSION_STRETCH, true],
            [OS\Debian::class, OS\Debian::VERSION_BUSTER, false],
            [OS\Alpine::class, 312, false],
        ];
    }

    /**
     * @dataProvider provideCompilerFlags
     *
     * @param class-string<\IPE\OS> $osClass
     * @param int $osVersion
     * @param bool $expected
     *
     * @return void
     */
    public function testCompilerFlags($osClass, $osVersion, $expected)
    {
        $os = M::mock($osClass);
        self::assertInstanceOf(OS::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        $flags = (new tensor(M::mock(PHPInfo::class), $os))->getCompilerFlags();
        self::assertStringContainsString('-Wno-incompatible-pointer-types', $flags);
        self::assertSame($expected, strpos($flags, '-I/usr/include/openblas') !== false);
    }
}
