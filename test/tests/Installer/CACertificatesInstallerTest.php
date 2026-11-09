<?php

namespace IPE\Test\Installer;

use IPE\Filesystem;
use IPE\Installer\CACertificatesInstaller;
use IPE\Installer\State;
use IPE\OS;
use IPE\PHPInfo;
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
     * @return \IPE\Installer\State
     */
    private static function createState()
    {
        return new State(M::mock(PHPInfo::class), M::mock(Filesystem::class));
    }

    /**
     * @return void
     */
    public function testAlreadyAvailable()
    {
        list($os, $packager) = self::createOS(true);
        $state = self::createState();
        (new CACertificatesInstaller($os, new \IPE\Logger()))->install($state);
        $state->shutdown(new \IPE\Logger());
        self::assertSame([], $packager->log);
    }

    /**
     * @return void
     */
    public function testInstallUntilShutdown()
    {
        list($os, $packager) = self::createOS(false);
        $state = self::createState();
        (new CACertificatesInstaller($os, new \IPE\Logger()))->install($state);
        self::assertSame(
            [
                'list',
                'refresh',
                'expand ca-certificates',
                'install ca-certificates=1.0 openssl=1.0',
            ],
            $packager->log
        );
        $packager->log = [];
        $state->shutdown(new \IPE\Logger());
        self::assertSame(
            [
                'remove ca-certificates openssl',
                'restore libc6',
            ],
            $packager->log
        );
    }
}
