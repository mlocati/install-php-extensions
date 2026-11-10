<?php

namespace IPE\Test\SystemPackager;

use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\SystemPackager\APK;
use IPE\SystemPackager\APT;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class AvailablePackageTest extends TestCase
{
    /**
     * @param list<array{non-empty-string, list<string>, int, string}> $runs the command, its arguments, its exit code and its output
     *
     * @return \IPE\OS&\Mockery\MockInterface
     */
    private static function createOS(Command $refresh, array $runs)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('run')->once()->with(self::isCommand($refresh->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        foreach ($runs as list($command, $arguments, $rc, $output)) {
            $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create($command, $arguments)->withOutput(Command::OUTPUT_CAPTURE_STDOUT)))->andReturn([$rc, $output]);
        }
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);

        return $os;
    }

    /**
     * @param list<array{non-empty-string, list<string>, int, string}> $runs
     *
     * @return \IPE\SystemPackager\APT
     */
    private static function createAPT(array $runs)
    {
        return new APT(self::createOS(Command::create('apt-get', ['update', '-qy']), $runs));
    }

    /**
     * @param list<array{non-empty-string, list<string>, int, string}> $runs
     *
     * @return \IPE\SystemPackager\APK
     */
    private static function createAPK(array $runs)
    {
        return new APK(self::createOS(Command::create('apk', ['update']), $runs));
    }

    /**
     * @return void
     */
    public function testAPT()
    {
        $apt = self::createAPT([
            ['apt-cache', ['show', '--no-all-versions', 'php8.4-mysql'], 0, "Package: php8.4-mysql\nSource: php8.4\nVersion: 8.4.27-1~deb13u1\nArchitecture: amd64\n"],
            ['apt-cache', ['show', '--no-all-versions', 'php8.4-redis'], 0, "Package: php8.4-redis\nSource: php-redis (6.2.0-1)\nVersion: 6.2.0-1+b1\n"],
            ['apt-cache', ['show', '--no-all-versions', 'php-redis'], 0, "Package: php-redis\nVersion: 6.2.0-1\n"],
        ]);
        $package = $apt->findAvailablePackage('php8.4-mysql');
        self::assertNotNull($package);
        self::assertSame('php8.4-mysql', $package->getName());
        self::assertSame('8.4.27-1~deb13u1', $package->getVersion());
        self::assertSame('php8.4', $package->getSource());
        // The package lists are refreshed only once
        $package = $apt->findAvailablePackage('php8.4-redis');
        self::assertNotNull($package);
        self::assertSame('6.2.0-1+b1', $package->getVersion());
        self::assertSame('php-redis', $package->getSource());
        $package = $apt->findAvailablePackage('php-redis');
        self::assertNotNull($package);
        self::assertSame('php-redis', $package->getSource());
    }

    /**
     * @return void
     */
    public function testAPTVersionNotAvailable()
    {
        $apt = self::createAPT([
            ['apt-cache', ['show', '--no-all-versions', 'php8.4-mysql=8.4.26-1'], 100, ''],
            ['apt-cache', ['showpkg', 'php8.4-mysql'], 0, "Package: php8.4-mysql\nVersions: \n8.4.27-1~deb13u1 (/var/lib/apt/lists/deb.debian.org_debian_dists_trixie_main_binary-amd64_Packages)\n\nReverse Provides: \n"],
        ]);
        self::assertNull($apt->findAvailablePackage('php8.4-mysql', '8.4.26-1'));
    }

    /**
     * @return void
     */
    public function testAPTVirtual()
    {
        $apt = self::createAPT([
            ['apt-cache', ['show', '--no-all-versions', 'php7.4-redis'], 100, ''],
            ['apt-cache', ['showpkg', 'php7.4-redis'], 0, "Package: php7.4-redis\nVersions: \n\nReverse Depends: \nDependencies: \nProvides: \nReverse Provides: \nphp-redis 5.1.1+4.3.0-1 (= )\n"],
            ['apt-cache', ['show', '--no-all-versions', 'php-redis'], 0, "Package: php-redis\nVersion: 5.1.1+4.3.0-1\n"],
        ]);
        $package = $apt->findAvailablePackage('php7.4-redis');
        self::assertNotNull($package);
        self::assertSame('php-redis', $package->getName());
        self::assertSame('5.1.1+4.3.0-1', $package->getVersion());
    }

    /**
     * @return void
     */
    public function testAPTVirtualWithManyProviders()
    {
        $apt = self::createAPT([
            ['apt-cache', ['show', '--no-all-versions', 'php-foo'], 100, ''],
            ['apt-cache', ['showpkg', 'php-foo'], 0, "Package: php-foo\nReverse Provides: \nphp-foo-a 1.0-1 (= )\nphp-foo-b 2.0-1 (= )\n"],
        ]);
        self::assertNull($apt->findAvailablePackage('php-foo'));
    }

    /**
     * @return void
     */
    public function testAPTNotAvailable()
    {
        $apt = self::createAPT([
            ['apt-cache', ['show', '--no-all-versions', 'php8.4-nope'], 100, ''],
            ['apt-cache', ['showpkg', 'php8.4-nope'], 0, "N: Unable to locate package php8.4-nope\n"],
        ]);
        self::assertNull($apt->findAvailablePackage('php8.4-nope'));
    }

    /**
     * @return list<array{non-empty-string, string}>
     */
    public static function provideAPTUpstreamVersionCases()
    {
        return [
            ['6.2.0-1', '6.2.0'],
            ['6.2.0-1+b1', '6.2.0'],
            ['8.4.27-1~deb13u1', '8.4.27'],
            ['5.3.7+4.3.0-3ubuntu1', '5.3.7'],
            ['2:3.1.6+dfsg-1', '3.1.6'],
            ['1.0.0~rc1-1', '1.0.0'],
            ['2.0.0RC1-1', '2.0.0RC1'],
            ['20', '20'],
            ['abc', ''],
        ];
    }

    /**
     * @dataProvider provideAPTUpstreamVersionCases
     *
     * @param non-empty-string $version
     * @param string $expected
     *
     * @return void
     */
    public function testAPTUpstreamVersion($version, $expected)
    {
        self::assertSame($expected, (new APT(M::mock(OS::class)))->getUpstreamVersion($version));
    }

    /**
     * @return void
     */
    public function testAPK()
    {
        $apk = self::createAPK([
            ['apk', ['search', '--exact', 'php83-redis'], 0, "php83-pecl-redis-6.3.0-r0\n"],
            ['apk', ['list', '--available', 'php83-pecl-redis'], 0, "php83-pecl-redis-6.3.0-r0 x86_64 {php83-pecl-redis} (PHP-3.01)\n"],
            ['apk', ['search', '--exact', '--all', 'php83-pdo_mysql'], 0, "php83-pdo_mysql-8.3.37-r0\nphp83-pdo_mysql-8.3.36-r0\n"],
            ['apk', ['list', '--available', 'php83-pdo_mysql'], 0, "php83-pdo_mysql-8.3.37-r0 x86_64 {php83} (PHP-3.01)\nphp83-pdo_mysql-8.3.36-r0 x86_64 {php83} (PHP-3.01) [installed]\n"],
        ]);
        $package = $apk->findAvailablePackage('php83-redis');
        self::assertNotNull($package);
        self::assertSame('php83-pecl-redis', $package->getName());
        self::assertSame('6.3.0-r0', $package->getVersion());
        self::assertSame('php83-pecl-redis', $package->getSource());
        $package = $apk->findAvailablePackage('php83-pdo_mysql', '8.3.36-r0');
        self::assertNotNull($package);
        self::assertSame('php83-pdo_mysql', $package->getName());
        self::assertSame('8.3.36-r0', $package->getVersion());
        self::assertSame('php83', $package->getSource());
    }

    /**
     * @return void
     */
    public function testAPKNotAvailable()
    {
        $apk = self::createAPK([
            ['apk', ['search', '--exact', 'php83-nope'], 0, ''],
            ['apk', ['search', '--exact', '--all', 'php83-mysqli'], 0, "php83-mysqli-8.3.37-r0\n"],
        ]);
        self::assertNull($apk->findAvailablePackage('php83-nope'));
        self::assertNull($apk->findAvailablePackage('php83-mysqli', '8.3.36-r0'));
    }

    /**
     * @return list<array{non-empty-string, string}>
     */
    public static function provideAPKUpstreamVersionCases()
    {
        return [
            ['6.3.0-r0', '6.3.0'],
            ['8.4.0_rc1-r0', '8.4.0'],
            ['3.1.6_p2-r1', '3.1.6'],
            ['1.2.3a-r1', '1.2.3a'],
            ['abc', ''],
        ];
    }

    /**
     * @dataProvider provideAPKUpstreamVersionCases
     *
     * @param non-empty-string $version
     * @param string $expected
     *
     * @return void
     */
    public function testAPKUpstreamVersion($version, $expected)
    {
        self::assertSame($expected, (new APK(M::mock(OS::class)))->getUpstreamVersion($version));
    }
}
