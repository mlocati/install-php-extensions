<?php

namespace IPE\Test\Extensions;

use IPE\Exception;
use IPE\Extensions\swoole;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class SwooleTest extends TestCase
{
    /**
     * @param int $phpVersion
     * @param class-string<\IPE\OS> $osClass
     * @param int $osVersion
     * @param bool $threadSafe
     * @param int $bits
     * @param list<string> $installed
     *
     * @return \IPE\Extensions\swoole
     */
    private static function createExtension($phpVersion, $osClass, $osVersion, $threadSafe = false, $bits = 64, array $installed = [])
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
        $php->shouldReceive('isThreadSafe')->andReturn($threadSafe);
        $php->shouldReceive('getBits')->andReturn($bits);
        $php->shouldReceive('getInstalledExtensions')->andReturn($installed);
        $os = M::mock($osClass);
        self::assertInstanceOf(OS::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);

        return new swoole($php, $os);
    }

    /**
     * @return array<array{int, class-string<\IPE\OS>, int, int, string}>
     */
    public static function provideDefaultVersions()
    {
        return [
            [505, OS\Debian::class, OS\Debian::JESSIE, 64, '2.0.10'],
            [506, OS\Alpine::class, 308, 64, '2.0.11'],
            [700, OS\Debian::class, OS\Debian::STRETCH, 64, '4.3.6'],
            [701, OS\Debian::class, OS\Debian::BUSTER, 64, '4.5.10'],
            [704, OS\Debian::class, OS\Debian::BULLSEYE, 64, '4.8.13'],
            [704, OS\Debian::class, OS\Debian::BULLSEYE, 32, '4.8.13'],
            [802, OS\Debian::class, OS\Debian::BOOKWORM, 32, '5.0.3'],
            [802, OS\Alpine::class, 320, 32, '5.0.3'],
            [800, OS\Alpine::class, 312, 64, '5.1.2'],
            [801, OS\Alpine::class, 315, 64, '5.1.8'],
            [800, OS\Debian::class, OS\Debian::BULLSEYE, 64, '5.1.3'],
            [801, OS\Debian::class, OS\Debian::BOOKWORM, 64, '6.1.7'],
            [802, OS\Alpine::class, 321, 64, ''],
            [804, OS\Debian::class, OS\Debian::TRIXIE, 64, ''],
        ];
    }

    /**
     * @dataProvider provideDefaultVersions
     *
     * @param int $phpVersion
     * @param class-string<\IPE\OS> $osClass
     * @param int $osVersion
     * @param int $bits
     * @param string $expected
     *
     * @return void
     */
    public function testDefaultVersion($phpVersion, $osClass, $osVersion, $bits, $expected)
    {
        self::assertSame($expected, self::createExtension($phpVersion, $osClass, $osVersion, false, $bits)->getDefaultVersionRequirements());
    }

    /**
     * @return void
     */
    public function testNoDefaultVersionFor32BitsWithRecentPHP()
    {
        $this->expectException(Exception\UnsupportedExtension::class);
        self::createExtension(803, OS\Debian::class, OS\Debian::TRIXIE, false, 32)->getDefaultVersionRequirements();
    }

    /**
     * @return array<array{class-string<\IPE\OS>, int, string, string, list<string>}>
     */
    public static function provideBuildArguments()
    {
        return [
            [OS\Debian::class, 32, OS\Architecture::X64, '5.0.3', ['--build=i686-linux-gnu']],
            [OS\Alpine::class, 32, OS\Architecture::X64, '5.0.3', ['--build=i686-linux-musl']],
            [OS\Debian::class, 32, OS\Architecture::X64, '2.0.10', ['--build=i686-linux-gnu']],
            [OS\Debian::class, 32, OS\Architecture::X86, '5.0.3', []],
            [OS\Debian::class, 32, OS\Architecture::ARM64, '5.0.3', []],
            [OS\Debian::class, 64, OS\Architecture::X64, '5.0.3', []],
        ];
    }

    /**
     * @dataProvider provideBuildArguments
     *
     * @param class-string<\IPE\OS> $osClass
     * @param int $bits
     * @param string $architecture
     * @param string $version
     * @param list<string> $expected
     *
     * @return void
     */
    public function testBuildArguments($osClass, $bits, $architecture, $version, array $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getBits')->andReturn($bits);
        $php->shouldReceive('isThreadSafe')->andReturn(false);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(802);
        $php->shouldReceive('getInstalledExtensions')->andReturn([]);
        $os = M::mock($osClass);
        self::assertInstanceOf(OS::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn(320);
        $os->shouldReceive('getMachineArchitecture')->andReturn($architecture);
        $arguments = (new swoole($php, $os))->getConfigureArguments(self::createBuildContext($version));
        self::assertSame($expected, array_values(array_filter($arguments, static function ($argument) {
            return strpos($argument, '--build=') === 0;
        })));
    }

    /**
     * @return array<array{string, list<string>}>
     */
    public static function provideConfigureArguments()
    {
        return [
            ['2.0.10', []],
            ['2.1.2', ['--enable-mysqlnd', '--enable-openssl', '--enable-http2', '--enable-coroutine-postgresql']],
            ['4.3.6', ['--enable-mysqlnd', '--enable-openssl', '--enable-http2', '--enable-coroutine-postgresql']],
            ['4.4.0', ['--enable-mysqlnd', '--enable-openssl', '--enable-http2']],
            ['4.5.6', ['--enable-mysqlnd', '--enable-openssl', '--enable-http2', '--enable-swoole-json']],
            ['4.6.0', ['--enable-mysqlnd', '--enable-openssl', '--enable-http2', '--enable-swoole-json', '--enable-swoole-curl']],
            ['4.8.11', ['--enable-mysqlnd', '--enable-openssl', '--enable-http2', '--enable-swoole-json', '--enable-swoole-curl', '--enable-cares']],
            ['5.0.0', ['--enable-mysqlnd', '--enable-openssl', '--enable-swoole-pgsql', '--enable-swoole-curl', '--enable-cares']],
            ['5.1.0', ['--enable-mysqlnd', '--enable-openssl', '--enable-swoole-pgsql', '--enable-swoole-curl', '--enable-cares', '--enable-brotli', '--enable-swoole-sqlite']],
            ['6.0.0', ['--enable-mysqlnd', '--enable-openssl', '--enable-swoole-pgsql', '--enable-swoole-curl', '--enable-cares', '--enable-brotli', '--enable-swoole-sqlite', '--enable-zstd', '--enable-swoole-thread', '--enable-iouring']],
            ['6.2.0', ['--enable-mysqlnd', '--enable-swoole-pgsql', '--enable-swoole-curl', '--enable-cares', '--enable-brotli', '--enable-swoole-sqlite', '--enable-zstd', '--enable-swoole-thread', '--enable-iouring', '--enable-uring-socket', '--with-swoole-firebird', '--with-swoole-ssh2']],
            ['', ['--enable-mysqlnd', '--enable-swoole-pgsql', '--enable-swoole-curl', '--enable-cares', '--enable-brotli', '--enable-swoole-sqlite', '--enable-zstd', '--enable-swoole-thread', '--enable-iouring', '--enable-uring-socket', '--with-swoole-firebird', '--with-swoole-ssh2']],
        ];
    }

    /**
     * @dataProvider provideConfigureArguments
     *
     * @param string $version
     * @param list<string> $expected
     *
     * @return void
     */
    public function testConfigureArguments($version, array $expected)
    {
        self::assertSame($expected, self::createExtension(804, OS\Debian::class, OS\Debian::TRIXIE, true)->getConfigureArguments(self::createBuildContext($version)));
    }

    /**
     * @return void
     */
    public function testThreadSupportRequiresZTS()
    {
        self::assertNotContains('--enable-swoole-thread', self::createExtension(804, OS\Debian::class, OS\Debian::TRIXIE)->getConfigureArguments(self::createBuildContext('6.2.0')));
    }

    /**
     * @return array<array{class-string<\IPE\OS>, int, string, bool}>
     */
    public static function provideIOUring()
    {
        return [
            [OS\Debian::class, OS\Debian::BOOKWORM, '6.2.0', false],
            [OS\Debian::class, OS\Debian::TRIXIE, '5.1.0', false],
            [OS\Debian::class, OS\Debian::TRIXIE, '6.0.0', true],
            [OS\Alpine::class, 319, '6.0.0', false],
            [OS\Alpine::class, 320, '6.0.0', true],
            [OS\Alpine::class, 320, '6.2.0', false],
            [OS\Alpine::class, 321, '6.2.0', true],
        ];
    }

    /**
     * @dataProvider provideIOUring
     *
     * @param class-string<\IPE\OS> $osClass
     * @param int $osVersion
     * @param string $version
     * @param bool $expected
     *
     * @return void
     */
    public function testIOUring($osClass, $osVersion, $version, $expected)
    {
        $extension = self::createExtension(804, $osClass, $osVersion);
        self::assertSame($expected, in_array('--enable-iouring', $extension->getConfigureArguments(self::createBuildContext($version)), true));
        self::assertSame($expected, in_array(SystemDependencies\liburing::HANDLE, $extension->getRequiredSystemDependencies($version, new Values()), true));
        $without = new Values([], [swoole::OPTION_WITHOUT_IOURING => true]);
        self::assertNotContains('--enable-iouring', $extension->getConfigureArguments(self::createBuildContext($version, $without)));
        self::assertNotContains(SystemDependencies\liburing::HANDLE, $extension->getRequiredSystemDependencies($version, $without));
    }

    /**
     * @return void
     */
    public function testDatabasesAndCompressionDependOnTheEnvironment()
    {
        $old = self::createExtension(801, OS\Debian::class, OS\Debian::BUSTER);
        $arguments = $old->getConfigureArguments(self::createBuildContext('6.1.0'));
        self::assertNotContains('--enable-zstd', $arguments);
        self::assertContains('--enable-swoole-sqlite', $arguments);
        $dependencies = $old->getRequiredSystemDependencies('6.1.0', new Values());
        self::assertNotContains(SystemDependencies\libzstd::HANDLE, $dependencies);
        self::assertContains(SystemDependencies\libsqlite::HANDLE, $dependencies);
        self::assertContains(SystemDependencies\libzlib::HANDLE, $dependencies);
        self::assertNotContains('--with-swoole-firebird', self::createExtension(803, OS\Alpine::class, 322)->getConfigureArguments(self::createBuildContext('6.2.0')));
        self::assertContains('--with-swoole-firebird', self::createExtension(804, OS\Alpine::class, 322)->getConfigureArguments(self::createBuildContext('6.2.0')));
        self::assertContains(SystemDependencies\libfbclient::HANDLE, self::createExtension(804, OS\Alpine::class, 322)->getRequiredSystemDependencies('6.2.0', new Values()));
    }

    /**
     * @return void
     */
    public function testSocketsSupport()
    {
        $without = self::createExtension(804, OS\Debian::class, OS\Debian::TRIXIE);
        self::assertNotContains('--enable-sockets', $without->getConfigureArguments(self::createBuildContext('6.2.0')));
        self::assertSame(['sockets'], $without->getOptionalExtensions('6.2.0'));
        $with = self::createExtension(804, OS\Debian::class, OS\Debian::TRIXIE, false, 64, ['Core', 'sockets']);
        self::assertContains('--enable-sockets', $with->getConfigureArguments(self::createBuildContext('6.2.0')));
        self::assertSame([], $with->getConfigureArguments(self::createBuildContext('2.0.10')));
    }
}
