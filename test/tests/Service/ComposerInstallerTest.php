<?php

namespace IPE\Test\Service;

use IPE\Exception;
use IPE\Extension\Registry;
use IPE\Filesystem;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\ComposerInstaller;
use IPE\Service\PHPScript;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ComposerInstallerTest extends TestCase
{
    /**
     * @return array<array{string, string, bool}> the output of composer --version, the requested version, and whether Composer should be installed
     */
    public static function provideAlreadyInstalledCases()
    {
        return [
            ["Composer version 2.11.3 2026-11-10 10:00:00\n", '', false],
            ["Composer version 2.11.3 2026-11-10 10:00:00\n", 'stable', false],
            ["Composer version 2.11.3 2026-11-10 10:00:00\nPHP version 8.4.28 (/usr/bin/php)\n", '2', false],
            ["Composer version 2.11.3 2026-11-10 10:00:00\n", '2.11.3', false],
            ["Composer version 2.11.3 2026-11-10 10:00:00\n", '2.2', true],
            ["Composer version 2.11.3 2026-11-10 10:00:00\n", '2.2.0', true],
            ["Composer version 2.11.3 2026-11-10 10:00:00\n", '1', true],
            ["Composer version 2.2.25 2025-04-01 10:00:00\n", '2.2', false],
            ["Composer version 2.20.1 2028-01-01 10:00:00\n", '2.2', true],
            ["Composer 1.10.27 2023-09-29 10:00:00\n", '1', false],
            ["Something unexpected\n", '2', true],
        ];
    }

    /**
     * @dataProvider provideAlreadyInstalledCases
     *
     * @param string $versionOutput
     * @param string $requestedVersion
     * @param bool $shouldInstall
     *
     * @return void
     */
    public function testAlreadyInstalled($versionOutput, $requestedVersion, $shouldInstall)
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('composer')->andReturn('/usr/bin/composer');
        $fs->shouldReceive('run')->andReturn([0, $versionOutput]);
        $fs->shouldReceive('isDirectory')->with('/usr/local/bin')->andReturn(true);
        self::assertSame($shouldInstall, self::install($fs, '', $requestedVersion));
    }

    /**
     * @return void
     */
    public function testAlreadyInstalledToInstallPath()
    {
        $fs = self::createFilesystem();
        $fs->shouldNotReceive('resolveCommand');
        $fs->shouldReceive('isDirectory')->with('/opt/bin')->andReturn(true);
        $fs->shouldReceive('isFile')->with('/opt/bin/composer')->andReturn(true);
        $fs->shouldReceive('run')->andReturnUsing(static function (Filesystem\Command $command) {
            self::assertSame('/usr/bin/php', $command->getCommand());
            self::assertSame('/opt/bin/composer', $command->getArguments()[0]);

            return [0, "Composer version 2.2.0 2021-12-22 22:22:22\n"];
        });
        self::assertFalse(self::install($fs, '/opt/bin', '2.2.0'));
    }

    /**
     * @return void
     */
    public function testCreateUsrLocalBinInPath()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('composer')->andReturn('');
        $fs->shouldReceive('isDirectory')->with('/usr/local/bin')->andReturn(false);
        $fs->shouldReceive('createDirectory')->with('/usr/local/bin', 0755)->once();
        $path = getenv('PATH');
        putenv('PATH=/usr/local/sbin:/usr/local/bin:/usr/bin');
        try {
            self::assertTrue(self::install($fs, '', ''));
        } finally {
            putenv("PATH={$path}");
        }
    }

    /**
     * @return void
     */
    public function testDontCreateUsrLocalBinNotInPath()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('composer')->andReturn('');
        $fs->shouldReceive('isDirectory')->with('/usr/local/bin')->andReturn(false);
        $fs->shouldNotReceive('createDirectory');
        $path = getenv('PATH');
        putenv('PATH=/usr/local/bin/foo:/usr/bin');
        try {
            self::install($fs, '', '');
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertStringContainsString('/usr/local/bin', $x->getMessage());
        } finally {
            putenv("PATH={$path}");
        }
    }

    /**
     * @return \Mockery\MockInterface&\IPE\Filesystem
     */
    private static function createFilesystem()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('getRealPath')->andReturnUsing(static function ($path) { return $path; });
        // Called just before downloading the Composer installer
        $fs->shouldReceive('createVolatileDirectory')->andThrow(new Exception\IO('Installing Composer'));

        return $fs;
    }

    /**
     * @param string $installPath
     * @param string $version
     *
     * @return bool true if Composer would be installed
     */
    private static function install(Filesystem $fs, $installPath, $version)
    {
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/bin/php');
        $registry = M::mock(Registry::class);
        $registry->shouldReceive('getExtensionClasses')->andReturn([]);
        $downloader = M::mock(Filesystem\Downloader::class);
        $installer = new ComposerInstaller($downloader, $os, $php, new PHPScript($php, $registry), new \IPE\Logger());
        try {
            $installer->install($installPath, $version);
        } catch (Exception\IO $x) {
            if ($x->getMessage() !== 'Installing Composer') {
                throw $x;
            }

            return true;
        }

        return false;
    }
}
