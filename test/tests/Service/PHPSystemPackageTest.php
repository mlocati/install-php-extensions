<?php

namespace IPE\Test\Service;

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
