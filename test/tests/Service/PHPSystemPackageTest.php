<?php

namespace IPE\Test\Service;

use IPE\Exception;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\PHPSystemPackage;
use IPE\SystemPackager;
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
        $package = new SystemPackager\InstalledPackage('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4');
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
     * @return list<array{class-string<\IPE\OS\Posix>, \IPE\SystemPackager\InstalledPackage, string}>
     */
    public static function provideDevelopmentPackageCases()
    {
        return [
            [OS\Debian::class, new SystemPackager\InstalledPackage('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4'), 'php8.4-dev=8.4.27-1~deb13u1'],
            [OS\Ubuntu::class, new SystemPackager\InstalledPackage('php5-cli', '5.5.9+dfsg-1ubuntu4.29', 'php5'), 'php5-dev=5.5.9+dfsg-1ubuntu4.29'],
            [OS\Alpine::class, new SystemPackager\InstalledPackage('php84', '8.4.27-r0', 'php84'), 'php84-dev=8.4.27-r0'],
            [OS\Posix::class, new SystemPackager\InstalledPackage('php', '8.4.27', 'php'), ''],
        ];
    }

    /**
     * @dataProvider provideDevelopmentPackageCases
     *
     * @param class-string<\IPE\OS\Posix> $osClass
     * @param string $expected empty string if the OS is not supported
     *
     * @return void
     */
    public function testDevelopmentPackage($osClass, SystemPackager\InstalledPackage $package, $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/bin/php');
        $packager = M::mock(SystemPackager::class);
        $packager->shouldReceive('getInstalledPackageOwningFile')->andReturn($package);
        /** @var \IPE\OS\Posix&\Mockery\MockInterface $os */
        $os = M::mock($osClass);
        $os->shouldReceive('getSystemPackager')->andReturn($packager);
        $subject = new PHPSystemPackage($php, $os);
        if ($expected === '') {
            $this->expectException(Exception\UnsupportedOS::class);
        }
        self::assertSame($expected, $subject->getDevelopmentPackage());
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
     * @return void
     */
    public function testNotPosix()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->never();
        self::assertNull((new PHPSystemPackage($php, M::mock(OS::class)))->getPackage());
    }
}
