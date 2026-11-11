<?php

namespace IPE\Test\Extensions;

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
}
