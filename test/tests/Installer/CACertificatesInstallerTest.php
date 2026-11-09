<?php

namespace IPE\Test\Installer;

use IPE\Filesystem;
use IPE\Installer\CACertificatesInstaller;
use IPE\OS;
use IPE\Test\Support\DependencyInstallerTest\FakePackager;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class CACertificatesInstallerTest extends TestCase
{
    /**
     * @param bool $bundleExists
     *
     * @return array{0: \IPE\OS\Posix&\Mockery\MockInterface, 1: \IPE\Test\Support\DependencyInstallerTest\FakePackager}
     */
    private static function createOS($bundleExists)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('isFile')->with('/etc/ssl/certs/ca-certificates.crt')->andReturn($bundleExists);
        $os = M::mock(OS\Posix::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $packager = new FakePackager($os, ['libc6'], ['ca-certificates' => ['ca-certificates', 'openssl']]);
        $os->shouldReceive('getSystemPackager')->andReturn($packager);

        return [$os, $packager];
    }

    /**
     * @return void
     */
    public function testAlreadyAvailable()
    {
        list($os, $packager) = self::createOS(true);
        (new CACertificatesInstaller($os, new \IPE\Logger()))->install();
        self::assertSame([], $packager->log);
    }

    /**
     * @return void
     */
    public function testInstall()
    {
        list($os, $packager) = self::createOS(false);
        (new CACertificatesInstaller($os, new \IPE\Logger()))->install();
        self::assertSame(
            [
                'list',
                'refresh',
                'expand ca-certificates',
                'install ca-certificates openssl',
            ],
            $packager->log
        );
    }
}
