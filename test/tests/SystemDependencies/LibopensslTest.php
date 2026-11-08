<?php

namespace IPE\Test\SystemDependencies;

use IPE\Installer\State;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\PHPInfo\SSLLibrary;
use IPE\SystemDependencies\libopenssl;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class LibopensslTest extends TestCase
{
    /**
     * @param class-string<\IPE\OS\Posix> $class
     * @param int $version
     * @param string $phpVersionText the value of OPENSSL_VERSION_TEXT in PHP
     *
     * @return \IPE\SystemPackager\Packages
     */
    private static function getPackages($class, $version, $phpVersionText = '')
    {
        $os = M::mock($class);
        self::assertInstanceOf(OS\Posix::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($version);
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getSSLLibrary')->andReturn(SSLLibrary::fromVersionText($phpVersionText));

        return (new libopenssl($php, $os, new Values()))->getSystemPackages(M::mock(State::class));
    }

    /**
     * @return void
     */
    public function testOldAlpineUsesTheLibraryOfPHP()
    {
        foreach ([
            'OpenSSL 1.1.1w  11 Sep 2023' => [['libssl1.1'], ['openssl-dev']],
            'OpenSSL 3.0.15 3 Sep 2024' => [['libssl3'], ['openssl-dev']],
            'LibreSSL 2.6.5' => [['libressl2.6-libssl'], ['libressl-dev']],
            '' => [['libssl1.1'], ['openssl-dev']],
        ] as $versionText => list($persistent, $volatile)) {
            $packages = self::getPackages(OS\Alpine::class, 316, $versionText);
            self::assertSame($persistent, $packages->getPersistent());
            self::assertSame($volatile, $packages->getVolatile());
        }
    }

    /**
     * @return void
     */
    public function testOldAlpineWithoutTheLibraryOfPHP()
    {
        foreach ([
            304 => 'libssl1.0',
            308 => 'libssl1.0',
            309 => 'libssl1.1',
            316 => 'libssl1.1',
        ] as $version => $persistent) {
            $packages = self::getPackages(OS\Alpine::class, $version);
            self::assertSame([$persistent], $packages->getPersistent());
            self::assertSame(['openssl-dev'], $packages->getVolatile());
        }
    }

    /**
     * @return void
     */
    public function testStretchUsesTheLibraryOfPHP()
    {
        foreach ([
            'OpenSSL 1.0.2q  20 Nov 2018' => [['libssl1.0.2'], ['libssl1.0-dev']],
            'OpenSSL 1.1.0j  20 Nov 2018' => [['libssl1.1'], ['libssl-dev']],
            '' => [['libssl1.1'], ['libssl-dev']],
        ] as $versionText => list($persistent, $volatile)) {
            $packages = self::getPackages(OS\Debian::class, OS\Debian::VERSION_STRETCH, $versionText);
            self::assertSame($persistent, $packages->getPersistent());
            self::assertSame($volatile, $packages->getVolatile());
        }
        $packages = self::getPackages(OS\Debian::class, OS\Debian::VERSION_JESSIE, 'OpenSSL 1.0.1t  3 May 2016');
        self::assertSame(['libssl1.0.0'], $packages->getPersistent());
        self::assertSame(['libssl-dev'], $packages->getVolatile());
    }

    /**
     * @return void
     */
    public function testSystemPackages()
    {
        self::assertSame(['libssl3'], self::getPackages(OS\Alpine::class, 317)->getPersistent());
        self::assertSame(['libssl1.0.0'], self::getPackages(OS\Debian::class, OS\Debian::VERSION_JESSIE)->getPersistent());
        self::assertSame(['libssl1.1'], self::getPackages(OS\Debian::class, OS\Debian::VERSION_BULLSEYE)->getPersistent());
        self::assertSame(['libssl3'], self::getPackages(OS\Debian::class, OS\Debian::VERSION_BOOKWORM)->getPersistent());
        self::assertSame(['^libssl3(t64)?$'], self::getPackages(OS\Debian::class, OS\Debian::VERSION_TRIXIE)->getPersistent());
        self::assertSame(['libssl-dev'], self::getPackages(OS\Debian::class, OS\Debian::VERSION_TRIXIE)->getVolatile());
    }
}
