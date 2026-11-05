<?php

namespace IPE\Test\Installer;

use IPE\Exception;
use IPE\Installer\DependencyInstaller;
use IPE\OS;
use IPE\SystemPackager\Packages;
use IPE\Test\Support\DependencyInstallerTest\FakePackager;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class DependencyInstallerTest extends TestCase
{
    /**
     * @param list<non-empty-string> $installed
     * @param array<non-empty-string, list<non-empty-string>> $expansions
     *
     * @return \IPE\Test\Support\DependencyInstallerTest\FakePackager
     */
    private static function createPackager(array $installed, array $expansions)
    {
        return new FakePackager(M::mock(OS::class), $installed, $expansions);
    }

    /**
     * @return void
     */
    public function testNothingRequired()
    {
        $packager = self::createPackager(['libc6'], []);
        $installer = new DependencyInstaller($packager, new \IPE\Logger());
        $installed = $installer->install(new Packages());
        self::assertSame([], $installed->getPersistent());
        self::assertSame([], $installed->getVolatile());
        self::assertSame([], $installed->getPreviouslyInstalled());
        $installer->cleanup($installed, true);
        self::assertSame(['delete-cache'], $packager->log);
    }

    /**
     * @return void
     */
    public function testVolatileNeverIncludesPersistent()
    {
        $packager = self::createPackager(
            ['libc6', 'autoconf'],
            [
                'libzip4' => ['libzip4', 'libbz2-1.0'],
                'libzip-dev' => ['libzip-dev', 'libzip4', 'libbz2-1.0', 'zlib1g-dev'],
                'autoconf' => [],
            ]
        );
        $installer = new DependencyInstaller($packager, new \IPE\Logger());
        $installed = $installer->install(new Packages(['libzip4'], ['libzip-dev', 'autoconf']));
        self::assertSame(['libzip4', 'libbz2-1.0'], $installed->getPersistent());
        self::assertSame(['libzip-dev', 'zlib1g-dev'], $installed->getVolatile());
        self::assertSame(['libc6', 'autoconf'], $installed->getPreviouslyInstalled());
        self::assertSame(
            [
                'list',
                'refresh',
                'expand libzip4',
                'expand libzip4 libzip-dev autoconf',
                'install libzip4 libbz2-1.0 libzip-dev zlib1g-dev',
            ],
            $packager->log
        );
        $packager->log = [];
        $installer->cleanup($installed, false);
        self::assertSame(
            [
                'remove libzip-dev zlib1g-dev',
                'restore libc6 autoconf',
            ],
            $packager->log
        );
    }

    /**
     * @return void
     */
    public function testAlreadyInstalledPersistentPackagesAreMarked()
    {
        $packager = self::createPackager(
            ['libc6', 'libmcrypt4'],
            [
                'libmcrypt4' => [],
                'libmcrypt-dev' => ['libmcrypt-dev'],
            ]
        );
        $installer = new DependencyInstaller($packager, new \IPE\Logger());
        $installed = $installer->install(new Packages(['libmcrypt4'], ['libmcrypt-dev']));
        self::assertSame([], $installed->getPersistent());
        self::assertSame(['libmcrypt-dev'], $installed->getVolatile());
        self::assertSame(
            [
                'list',
                'refresh',
                'expand libmcrypt4',
                'expand libmcrypt4 libmcrypt-dev',
                'mark libmcrypt4',
                'install libmcrypt-dev',
            ],
            $packager->log
        );
    }

    /**
     * @return void
     */
    public function testRegularExpressionsMatchInstalledPackages()
    {
        $packager = self::createPackager(['libc6', 'libpng16-16', 'libpng-dev'], ['^libpng[0-9]+-[0-9]+$' => []]);
        (new DependencyInstaller($packager, new \IPE\Logger()))->install(new Packages(['^libpng[0-9]+-[0-9]+$']));
        self::assertContains('mark libpng16-16', $packager->log);
    }

    /**
     * @return void
     */
    public function testFailedInstallationIsRolledBack()
    {
        $packager = self::createPackager(['libc6'], ['lapack' => ['lapack'], 'openblas-dev' => ['openblas-dev', 'openblas']]);
        $packager->failInstall = true;
        try {
            (new DependencyInstaller($packager, new \IPE\Logger()))->install(new Packages(['lapack'], ['openblas-dev']));
            self::fail('The installation should have failed');
        } catch (Exception\SystemPackager $x) {
            self::assertSame('install failed', $x->getMessage());
        }
        self::assertSame(
            [
                'list',
                'refresh',
                'expand lapack',
                'expand lapack openblas-dev',
                'install lapack openblas-dev openblas',
                'remove lapack openblas-dev openblas',
                'restore libc6',
            ],
            $packager->log
        );
    }

    /**
     * @return void
     */
    public function testEverythingAlreadyInstalled()
    {
        $packager = self::createPackager(['libc6', 'libmcrypt4', 'libmcrypt-dev'], ['libmcrypt4' => [], 'libmcrypt-dev' => []]);
        $installer = new DependencyInstaller($packager, new \IPE\Logger());
        $installed = $installer->install(new Packages(['libmcrypt4'], ['libmcrypt-dev']));
        self::assertSame([], $installed->getVolatile());
        self::assertSame([], $installed->getPreviouslyInstalled());
        $packager->log = [];
        $installer->cleanup($installed, false);
        self::assertSame([], $packager->log);
    }
}
