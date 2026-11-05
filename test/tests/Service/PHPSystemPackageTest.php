<?php

namespace IPE\Test\Service;

use IPE\Extensions;
use IPE\Filesystem\Downloader;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\PHPSystemPackage;
use IPE\SystemPackager;
use IPE\Test\Support\DependencyInstallerTest\FakePackager;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class PHPSystemPackageTest extends TestCase
{
    /**
     * @return void
     */
    public function testPosix()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/bin/php8.4');
        $package = new SystemPackager\Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4');
        $packager = M::mock(SystemPackager::class);
        $packager->shouldReceive('getInstalledPackageOwningFile')->once()->with('/usr/bin/php8.4')->andReturn($package);
        $os = M::mock(OS\Posix::class);
        $os->shouldReceive('getSystemPackager')->andReturn($packager);
        $subject = new PHPSystemPackage($php, $os);
        self::assertSame($package, $subject->getPackage());
        // The result is cached
        self::assertSame($package, $subject->getPackage());
    }

    /**
     * @return void
     */
    public function testDevelopmentPackage()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/bin/php');
        $os = M::mock(OS\Posix::class);
        $packager = new FakePackager($os, [], []);
        $packager->owningPackage = new SystemPackager\Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4');
        $os->shouldReceive('getSystemPackager')->andReturn($packager);
        self::assertSame('php8.4-dev=8.4.27-1~deb13u1', (new PHPSystemPackage($php, $os))->getDevelopmentPackage());
    }

    /**
     * @return void
     */
    public function testNotFromAPackage()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/local/bin/php');
        $packager = M::mock(SystemPackager::class);
        $packager->shouldReceive('getInstalledPackageOwningFile')->once()->andReturnNull();
        $os = M::mock(OS\Posix::class);
        $os->shouldReceive('getSystemPackager')->andReturn($packager);
        $subject = new PHPSystemPackage($php, $os);
        self::assertNull($subject->getPackage());
        self::assertNull($subject->getPackage());
        self::assertSame('', $subject->getDevelopmentPackage());
    }

    /**
     * @param array<non-empty-string, list<\IPE\SystemPackager\Package>> $available
     *
     * @return \IPE\Service\PHPSystemPackage
     */
    private static function createWithAvailable(SystemPackager\Package $phpPackage, array $available)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/bin/php');
        $os = M::mock(OS\Posix::class);
        $packager = new FakePackager($os, [], []);
        $packager->owningPackage = $phpPackage;
        $packager->available = $available;
        $os->shouldReceive('getSystemPackager')->andReturn($packager);

        return new PHPSystemPackage($php, $os);
    }

    /**
     * @return void
     */
    public function testExtensionPackageBuiltWithPHP()
    {
        $phpPackage = new SystemPackager\Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4');
        $subject = self::createWithAvailable($phpPackage, [
            // Virtual package
            'php8.4-pdo-mysql' => [new SystemPackager\Package('php8.4-mysql', '8.4.27-1~deb13u1', 'php8.4')],
        ]);
        $package = $subject->findExtensionPackage(new Extensions\pdo_mysql(M::mock(PHPInfo::class), M::mock(OS::class)));
        self::assertNotNull($package);
        self::assertSame('php8.4-mysql', $package->getName());
        self::assertSame('8.4.27', $subject->getUpstreamVersion($package));
        // Otherwise the packager would upgrade PHP
        self::assertSame('php8.4-mysql=8.4.27-1~deb13u1', $subject->getInstallArgument($package));
    }

    /**
     * @return void
     */
    public function testExtensionPackageBuiltWithPHPNewerVersion()
    {
        $phpPackage = new SystemPackager\Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4');
        $subject = self::createWithAvailable($phpPackage, [
            'php8.4-calendar' => [
                new SystemPackager\Package('php8.4-common', '8.4.28-1~deb13u1', 'php8.4'),
            ],
            'php8.4-common' => [
                new SystemPackager\Package('php8.4-common', '8.4.28-1~deb13u1', 'php8.4'),
                new SystemPackager\Package('php8.4-common', '8.4.27-1~deb13u1', 'php8.4'),
            ],
        ]);
        $package = $subject->findExtensionPackage(new Extensions\calendar(M::mock(PHPInfo::class), M::mock(OS::class)));
        self::assertNotNull($package);
        self::assertSame('8.4.27-1~deb13u1', $package->getVersion());
        self::assertSame('php8.4-common=8.4.27-1~deb13u1', $subject->getInstallArgument($package));
    }

    /**
     * @return void
     */
    public function testExtensionPackageBuiltWithPHPOnlyNewerVersion()
    {
        $phpPackage = new SystemPackager\Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4');
        $subject = self::createWithAvailable($phpPackage, [
            'php8.4-calendar' => [new SystemPackager\Package('php8.4-common', '8.4.28-1~deb13u1', 'php8.4')],
            'php8.4-common' => [new SystemPackager\Package('php8.4-common', '8.4.28-1~deb13u1', 'php8.4')],
        ]);
        self::assertNull($subject->findExtensionPackage(new Extensions\calendar(M::mock(PHPInfo::class), M::mock(OS::class))));
    }

    /**
     * @return void
     */
    public function testExtensionPackageFromOtherSource()
    {
        $phpPackage = new SystemPackager\Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4');
        $subject = self::createWithAvailable($phpPackage, [
            'php8.4-redis' => [new SystemPackager\Package('php8.4-redis', '6.2.0-1', 'php-redis')],
        ]);
        $package = $subject->findExtensionPackage(new Extensions\redis(M::mock(PHPInfo::class), M::mock(OS::class)));
        self::assertNotNull($package);
        self::assertSame('6.2.0', $subject->getUpstreamVersion($package));
        self::assertSame('php8.4-redis', $subject->getInstallArgument($package));
    }

    /**
     * @return void
     */
    public function testExtensionPackageNotAvailable()
    {
        $phpPackage = new SystemPackager\Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4');
        $subject = self::createWithAvailable($phpPackage, []);
        self::assertNull($subject->findExtensionPackage(new Extensions\redis(M::mock(PHPInfo::class), M::mock(OS::class))));
    }

    /**
     * @return void
     */
    public function testExtensionNeverFromSystemPackages()
    {
        $phpPackage = new SystemPackager\Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4');
        $subject = self::createWithAvailable($phpPackage, [
            // It provides tideways_xhprof
            'php8.4-tideways' => [new SystemPackager\Package('php8.4-tideways', '5.0.4-1', 'php-tideways')],
        ]);
        self::assertNull($subject->findExtensionPackage(new Extensions\tideways(M::mock(PHPInfo::class), M::mock(OS::class), M::mock(Downloader::class))));
    }

    /**
     * @return void
     */
    public function testExtensionWithCustomSystemPackageNames()
    {
        $phpPackage = new SystemPackager\Package('php7.4-cli', '7.4.33-1+ubuntu24.04.1+deb.sury.org+1', 'php7.4');
        $subject = self::createWithAvailable($phpPackage, [
            'php7.4-phalcon5' => [new SystemPackager\Package('php7.4-phalcon5', '5.9.3-1+ubuntu24.04.1+deb.sury.org+1', 'php-phalcon5')],
            'php7.4-phalcon4' => [new SystemPackager\Package('php7.4-phalcon4', '4.1.2-1+ubuntu24.04.1+deb.sury.org+1', 'php-phalcon4')],
            // Dummy package that depends on php7.4-phalcon5
            'php7.4-phalcon' => [new SystemPackager\Package('php7.4-phalcon', '1:2+0~20260902.19+ubuntu24.04.1+deb.sury.org+1', 'php-phalcon')],
        ]);
        $os = M::mock(OS\Posix::class);
        $os->shouldReceive('getSystemPackager')->andReturn(new SystemPackager\APT(M::mock(OS::class)));
        $package = $subject->findExtensionPackage(new Extensions\phalcon(M::mock(PHPInfo::class), $os));
        self::assertNotNull($package);
        self::assertSame('php7.4-phalcon5', $package->getName());
    }

    /**
     * @return void
     */
    public function testExtensionWithAutomaticSystemPackageNames()
    {
        $os = M::mock(OS\Posix::class);
        $os->shouldReceive('getSystemPackager')->andReturn(new SystemPackager\APK(M::mock(OS::class)));
        self::assertNull((new Extensions\phalcon(M::mock(PHPInfo::class), $os))->getSystemPackageNames(new SystemPackager\Package('php84', '8.4.27-r0', 'php84')));
    }

    /**
     * @return void
     */
    public function testExtensionPackageWithoutPHPPackage()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/local/bin/php');
        $packager = M::mock(SystemPackager::class);
        $packager->shouldReceive('getInstalledPackageOwningFile')->andReturnNull();
        $packager->shouldReceive('findAvailablePackage')->never();
        $os = M::mock(OS\Posix::class);
        $os->shouldReceive('getSystemPackager')->andReturn($packager);
        self::assertNull((new PHPSystemPackage($php, $os))->findExtensionPackage(new Extensions\redis(M::mock(PHPInfo::class), M::mock(OS::class))));
    }

    /**
     * @return void
     */
    public function testNotPosix()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->never();
        $subject = new PHPSystemPackage($php, M::mock(OS::class));
        self::assertNull($subject->getPackage());
        self::assertSame('', $subject->getDevelopmentPackage());
        self::assertNull($subject->findExtensionPackage(new Extensions\redis(M::mock(PHPInfo::class), M::mock(OS::class))));
    }
}
