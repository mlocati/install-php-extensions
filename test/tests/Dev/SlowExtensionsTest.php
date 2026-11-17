<?php

namespace IPE\Test\Dev;

use IPE\Dev\SlowExtensions;
use IPE\Exception;
use IPE\Test\Support\TestCase;

final class SlowExtensionsTest extends TestCase
{
    /**
     * @return array<array{string, string, string, bool}> the extension, the operating system, the PHP version, and whether the extension is known to have no system package
     */
    public static function provideWithoutSystemPackage()
    {
        return [
            ['grpc', 'trixie-apt', '8.4', true],
            ['grpc:1.60.0', 'trixie-apt', '8.4', true],
            ['grpc', 'trixie-sury', '8.4', false],
            ['swoole', 'trixie-sury', '8.0', true],
            ['swoole', 'trixie-sury', '8.1', false],
            ['cassandra', 'alpine3.24-apk', '8.4', true],
            ['cassandra', 'almalinux9-remi', '7.4', false],
            ['grpc', 'almalinux10-dnf', '8.4', true],
            ['redis', 'trixie-apt', '8.4', false],
        ];
    }

    /**
     * @dataProvider provideWithoutSystemPackage
     *
     * @param string $extension
     * @param string $os
     * @param string $phpVersion
     * @param bool $expected
     *
     * @return void
     */
    public function testWithoutSystemPackage($extension, $os, $phpVersion, $expected)
    {
        self::assertSame($expected, SlowExtensions::isWithoutSystemPackage($extension, $os, $phpVersion));
    }

    /**
     * @return void
     */
    public function testSystemPackagesOfSlowExtensions()
    {
        self::assertSame(SlowExtensions::getHandles(), array_keys(SlowExtensions::getSystemPackages()));
    }

    /**
     * Downloads the package indexes (about 130 MB), and needs the zstd command to read the ones of Fedora.
     *
     * @group online
     *
     * @return void
     */
    public function testSystemPackagesAreUpToDate()
    {
        try {
            $expected = SlowExtensions::check();
        } catch (Exception\IO $x) {
            self::markTestSkipped($x->getMessage());
        }
        self::assertSame($expected, SlowExtensions::getSystemPackages(), 'The system packages in ' . SlowExtensions::class . ' must be updated');
    }
}
