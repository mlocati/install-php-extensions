<?php

namespace IPE\Test\Extensions;

use IPE\Extension\Resolved;
use IPE\Extensions\mongodb;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class MongodbTest extends TestCase
{
    /**
     * @return array<array{string, array<string, string>}> the version of the extension, and the expected configure environment
     */
    public static function provideConfigureEnvironment()
    {
        return [
            ['1.9.2', ['LDFLAGS' => '-lresolv']],
            ['1.15.3', ['LDFLAGS' => '-lresolv']],
            ['1.16.0', []],
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
        self::assertSame($expected, (new mongodb(M::mock(PHPInfo::class), M::mock(OS::class)))->getConfigureEnvironment(self::createBuildContext($version)));
    }

    /**
     * @return array<array{string, string, bool}> the origin, the version, and whether it can be used
     */
    public static function provideUsableVersions()
    {
        return [
            [Resolved::ORIGIN_PECL, '1.9.2', true],
            [Resolved::ORIGIN_COMPOSER, '1.20.1', false],
            [Resolved::ORIGIN_COMPOSER, '1.21.0', true],
            [Resolved::ORIGIN_SYSTEM_PACKAGE, '1.12.0', false],
            [Resolved::ORIGIN_SYSTEM_PACKAGE, '1.16.0', true],
        ];
    }

    /**
     * @dataProvider provideUsableVersions
     *
     * @param string $origin
     * @param string $version
     * @param bool $expected
     *
     * @return void
     */
    public function testCanUseVersion($origin, $version, $expected)
    {
        self::assertSame($expected, (new mongodb(M::mock(PHPInfo::class), M::mock(OS::class)))->canUseVersion($origin, $version));
    }
}
