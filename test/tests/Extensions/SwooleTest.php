<?php

namespace IPE\Test\Extensions;

use IPE\Exception;
use IPE\Extension\UnsupportedEnvironment;
use IPE\Extensions\swoole;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\Container;
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
    public function testUnsupportedOn32BitsWithRecentPHP()
    {
        $subject = new UnsupportedEnvironment(new OS\Factory(new Container(), new \IPE\Logger()));
        $os = M::mock(OS\Debian::class);
        foreach ([[802, 32, false], [803, 32, true], [805, 32, true], [803, 64, false]] as $case) {
            list($phpVersion, $bits, $expected) = $case;
            $php = M::mock(PHPInfo::class);
            $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
            $php->shouldReceive('getBits')->andReturn($bits);
            $matches = false;
            foreach (array_keys(swoole::getUnsupportedEnvironments()) as $environment) {
                $matches = $matches || $subject->matches($environment, $php, $os);
            }
            self::assertSame($expected, $matches, "PHP {$phpVersion} with {$bits} bits");
        }
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
            [OS\AlmaLinux::class, 10, '6.2.0', false],
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
    public function testSSH2()
    {
        $extension = self::createExtension(804, OS\Debian::class, OS\Debian::TRIXIE);
        $with = new Values();
        $without = new Values([], [swoole::OPTION_WITHOUT_SSH2 => true]);
        self::assertNotContains('--with-swoole-ssh2', $extension->getConfigureArguments(self::createBuildContext('6.1.7', $with)));
        self::assertNotContains(SystemDependencies\libssh2::HANDLE, $extension->getRequiredSystemDependencies('6.1.7', $with));
        self::assertSame(['openswoole'], array_keys($extension->getConflictingExtensions('6.1.7', $with)));
        self::assertContains('--with-swoole-ssh2', $extension->getConfigureArguments(self::createBuildContext('6.2.0', $with)));
        self::assertContains(SystemDependencies\libssh2::HANDLE, $extension->getRequiredSystemDependencies('6.2.0', $with));
        $conflicts = $extension->getConflictingExtensions('6.2.0', $with);
        self::assertSame(['openswoole', 'ssh2'], array_keys($conflicts));
        self::assertStringContainsString("'without-ssh2'", $conflicts['ssh2']);
        self::assertNotContains('--with-swoole-ssh2', $extension->getConfigureArguments(self::createBuildContext('6.2.0', $without)));
        self::assertNotContains(SystemDependencies\libssh2::HANDLE, $extension->getRequiredSystemDependencies('6.2.0', $without));
        self::assertSame(['openswoole'], array_keys($extension->getConflictingExtensions('6.2.0', $without)));
        // System packages may be built without the SSH2 client (like the ones of Remi)
        self::assertSame(['openswoole'], array_keys($extension->getConflictingExtensions('6.2.0')));
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
        self::assertContains('--with-swoole-firebird', self::createExtension(804, OS\AlmaLinux::class, 10)->getConfigureArguments(self::createBuildContext('6.2.0')));
        self::assertNotContains('--with-swoole-firebird', self::createExtension(804, OS\AmazonLinux::class, 2023)->getConfigureArguments(self::createBuildContext('6.2.0')));
        self::assertContains('--enable-zstd', self::createExtension(804, OS\AlmaLinux::class, 8)->getConfigureArguments(self::createBuildContext('6.2.0')));
        self::assertNotContains(SystemDependencies\libbrotli::HANDLE, self::createExtension(700, OS\Debian::class, OS\Debian::BUSTER)->getRequiredSystemDependencies('4.0.4', new Values()));
        self::assertNotContains(SystemDependencies\libbrotli::HANDLE, self::createExtension(704, OS\Debian::class, OS\Debian::STRETCH)->getRequiredSystemDependencies('4.8.13', new Values()));
        self::assertContains(SystemDependencies\libbrotli::HANDLE, self::createExtension(704, OS\Debian::class, OS\Debian::BUSTER)->getRequiredSystemDependencies('4.8.13', new Values()));
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

    /**
     * @return void
     */
    public function testUnknownOS()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(804);
        $php->shouldReceive('getBits')->andReturn(64);
        $php->shouldReceive('getInstalledExtensions')->andReturn([]);
        $os = M::mock(OS\Posix::class);
        $os->shouldReceive('getDisplayName')->andReturn('Test OS');
        $this->expectException(Exception\UnsupportedOS::class);
        $this->expectExceptionMessage('How to install swoole is not known for Test OS');
        (new swoole($php, $os))->getConfigureArguments(self::createBuildContext('6.2.0'));
    }
}
