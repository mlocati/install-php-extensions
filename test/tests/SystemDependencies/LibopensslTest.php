<?php

namespace IPE\Test\SystemDependencies;

use IPE\Filesystem;
use IPE\Installer\State;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\libopenssl;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class LibopensslTest extends TestCase
{
    /**
     * @param class-string<\IPE\OS\Posix> $class
     * @param int $version
     *
     * @return \IPE\SystemPackager\Packages
     */
    private static function getPackages($class, $version)
    {
        $os = M::mock($class);
        self::assertInstanceOf(OS\Posix::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($version);

        return (new libopenssl(M::mock(PHPInfo::class), $os, new Values()))->getSystemPackages(M::mock(State::class));
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
            '' => [[], []],
        ] as $version => list($persistent, $volatile)) {
            $fs = M::mock(Filesystem::class);
            $fs->shouldReceive('run')->once()->andReturn([0, $version]);
            $os = M::mock(OS\Alpine::class);
            $os->shouldReceive('getNumericVersion')->andReturn(316);
            $os->shouldReceive('getFilesystem')->andReturn($fs);
            $php = M::mock(PHPInfo::class);
            $php->shouldReceive('getPHPBin')->andReturn('/usr/local/bin/php');
            $packages = (new libopenssl($php, $os, new Values()))->getSystemPackages(M::mock(State::class));
            self::assertSame($persistent, $packages->getPersistent());
            self::assertSame($volatile, $packages->getVolatile());
        }
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
