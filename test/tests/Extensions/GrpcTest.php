<?php

namespace IPE\Test\Extensions;

use IPE\Architecture;
use IPE\Extensions\grpc;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class GrpcTest extends TestCase
{
    /**
     * @return array<array{string, string, string}> the extension version, and the expected flags for C and for C++
     */
    public static function provideMakeEnvironment()
    {
        return [
            ['1.33.1', grpc::WARNING_FLAGS . ' -Wno-error', grpc::WARNING_FLAGS . ' -include cstdint -include limits'],
            ['1.46.3', grpc::WARNING_FLAGS, grpc::WARNING_FLAGS . ' -include cstdint -include limits'],
            ['1.52.1', grpc::WARNING_FLAGS, grpc::WARNING_FLAGS],
            ['', grpc::WARNING_FLAGS, grpc::WARNING_FLAGS],
        ];
    }

    /**
     * @dataProvider provideMakeEnvironment
     *
     * @param string $version
     * @param string $expectedC
     * @param string $expectedCXX
     *
     * @return void
     */
    public function testMakeEnvironment($version, $expectedC, $expectedCXX)
    {
        $extension = new grpc(M::mock(PHPInfo::class), M::mock(OS::class));
        self::assertSame(['EXTRA_CFLAGS' => $expectedC, 'EXTRA_CXXFLAGS' => $expectedCXX], $extension->getMakeEnvironment(self::createBuildContext($version)));
    }

    /**
     * @return array<array{int, non-empty-string, string, string}> the AlmaLinux version, the raw architecture, the extension version, and the expected flags for C++
     */
    public static function provideEnterpriseLinuxMakeEnvironment()
    {
        return [
            [9, 'x86_64', '1.85.0', grpc::WARNING_FLAGS],
            [10, 'x86_64', '1.84.0', grpc::WARNING_FLAGS],
            [10, 'x86_64', '1.85.0', grpc::WARNING_FLAGS . ' -include x86gprintrin.h'],
            [10, 'aarch64', '1.85.0', grpc::WARNING_FLAGS],
        ];
    }

    /**
     * @dataProvider provideEnterpriseLinuxMakeEnvironment
     *
     * @param int $osVersion
     * @param non-empty-string $rawArchitecture
     * @param string $version
     * @param string $expectedCXX
     *
     * @return void
     */
    public function testEnterpriseLinuxMakeEnvironment($osVersion, $rawArchitecture, $version, $expectedCXX)
    {
        $os = M::mock(OS\AlmaLinux::class);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        $os->shouldReceive('getArchitecture')->andReturn(new Architecture($rawArchitecture));
        $php = self::mockPHPInfo($os);
        $extension = new grpc($php, $os);
        self::assertSame(['EXTRA_CFLAGS' => grpc::WARNING_FLAGS, 'EXTRA_CXXFLAGS' => $expectedCXX], $extension->getMakeEnvironment(self::createBuildContext($version)));
    }

    /**
     * @return void
     */
    public function testEnterpriseLinuxDefaultVersion()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(804);
        foreach ([8 => '1.84.0', 9 => ''] as $osVersion => $expected) {
            $os = M::mock(OS\AlmaLinux::class);
            $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
            self::assertSame($expected, (new grpc($php, $os))->getDefaultVersionRequirements());
        }
    }
}
