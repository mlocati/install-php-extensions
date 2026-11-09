<?php

namespace IPE\Test\Extensions;

use IPE\Exception;
use IPE\Extension\Registry;
use IPE\Extension\UnsupportedEnvironment;
use IPE\Extensions;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Filesystem\Downloader;
use IPE\Installer\BuildTools;
use IPE\Installer\State;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\Container;
use IPE\Service\JSON;
use IPE\Service\PHPScript;
use IPE\SystemDependencies;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class PrecompiledExtensionsTest extends TestCase
{
    /**
     * @param int $version
     * @param bool $threadSafe
     * @param int $bits
     * @param string $fullVersion empty string for <major>.<minor>.27
     *
     * @return \IPE\PHPInfo
     */
    private static function createPHP($version = 804, $threadSafe = false, $bits = 64, $fullVersion = '')
    {
        if ($fullVersion === '') {
            $fullVersion = (int) floor($version / 100) . '.' . ($version % 100) . '.27';
        }
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($version);
        $php->shouldReceive('getVersion')->andReturn($fullVersion);
        $php->shouldReceive('getBaseVersion')->andReturn($fullVersion);
        $php->shouldReceive('isThreadSafe')->andReturn($threadSafe);
        $php->shouldReceive('getBits')->andReturn($bits);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/local/bin/php');

        return $php;
    }

    /**
     * @param class-string<\IPE\OS> $class
     * @param int $version
     * @param \IPE\Filesystem|null $fs
     * @param string $architecture
     * @param string $rawArchitecture
     *
     * @return \IPE\OS
     */
    private static function createOS($class, $version, $fs = null, $architecture = OS\Architecture::ARM64, $rawArchitecture = 'aarch64')
    {
        $os = M::mock($class);
        self::assertInstanceOf(OS::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($version);
        $os->shouldReceive('getDisplayName')->andReturn('The OS');
        $os->shouldReceive('getMachineArchitecture')->andReturn($architecture);
        $os->shouldReceive('getRawMachineArchitecture')->andReturn($rawArchitecture);
        if ($fs !== null) {
            $os->shouldReceive('getFilesystem')->andReturn($fs);
        }

        return $os;
    }

    /**
     * @param list<string> $files the files that exist
     *
     * @return \IPE\Filesystem&\Mockery\MockInterface
     */
    private static function createFilesystem(array $files)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->andReturnUsing(static function ($path) use ($files) {
            return in_array($path, $files, true);
        });
        $fs->shouldReceive('isDirectory')->andReturnUsing(static function ($path) use ($files) {
            foreach ($files as $file) {
                if (is_string($path) && strpos($file, "{$path}/") === 0) {
                    return true;
                }
            }

            return false;
        });
        $fs->shouldReceive('listDirectoryContents')->andReturn($files);

        return $fs;
    }

    /**
     * @return void
     */
    public function testBlackfire()
    {
        $downloader = M::mock(Downloader::class);
        self::assertMatchesRegularExpression(
            '#^https://blackfire\.io/api/v1/releases/probe/php/alpine/\w+/84$#',
            (new Extensions\blackfire(self::createPHP(), self::createOS(OS\Alpine::class, 323), $downloader))->getBinaryArchiveURL('')
        );
        self::assertMatchesRegularExpression(
            '#^https://packages\.blackfire\.io/binaries/blackfire-php/1\.2\.3/blackfire-php-linux_\w+-php-74-zts\.tar\.gz$#',
            (new Extensions\blackfire(self::createPHP(704, true), self::createOS(OS\Debian::class, 12), $downloader))->getBinaryArchiveURL('1.2.3')
        );
        $php = self::createPHP();
        $os = self::createOS(OS\Debian::class, 12, self::createFilesystem(['/src/blackfire-20240924.sha1', '/src/blackfire-20240924.so']));
        self::assertSame('/src/blackfire-20240924.so', (new Extensions\blackfire($php, $os, $downloader))->findBinaryModule('/src', ''));
        $os = self::createOS(OS\Debian::class, 12, self::createFilesystem(['/src/README']));
        $extension = new Extensions\blackfire($php, $os, $downloader);
        self::assertSame('', $extension->findBinaryModule('/src', ''));
        self::assertSame("blackfire.agent_socket=tcp://blackfire:8307\n", $extension->getBinaryIniSettings(self::createBuildContext('', null, '/src')));
        self::assertFalse($extension->isZendExtension());
    }

    /**
     * @return array<array{string, string}>
     */
    public static function provideBlackfireArchitectures()
    {
        return [
            [OS\Architecture::X86, 'i386'],
            [OS\Architecture::X64, 'amd64'],
            [OS\Architecture::ARM64, 'arm64'],
        ];
    }

    /**
     * @dataProvider provideBlackfireArchitectures
     *
     * @param string $architecture
     * @param string $expected
     *
     * @return void
     */
    public function testBlackfireArchitectures($architecture, $expected)
    {
        $os = self::createOS(OS\Alpine::class, 323, null, $architecture);
        self::assertSame("https://blackfire.io/api/v1/releases/probe/php/alpine/{$expected}/84", (new Extensions\blackfire(self::createPHP(), $os, M::mock(Downloader::class)))->getBinaryArchiveURL(''));
    }

    /**
     * @return void
     */
    public function test32BitPHPOn64BitMachine()
    {
        $php = self::createPHP(804, false, 32);
        $downloader = M::mock(Downloader::class);
        $x64 = self::createOS(OS\Alpine::class, 323, null, OS\Architecture::X64, 'x86_64');
        self::assertSame('https://blackfire.io/api/v1/releases/probe/php/alpine/i386/84', (new Extensions\blackfire($php, $x64, $downloader))->getBinaryArchiveURL(''));
        self::assertStringContainsString('linux-i386', (new Extensions\sourceguardian($php, $x64, $downloader))->getBinaryArchiveURL(''));
        $arm64 = self::createOS(OS\Alpine::class, 323, null, OS\Architecture::ARM64, 'aarch64');
        self::assertSame('https://blackfire.io/api/v1/releases/probe/php/alpine/armv7l/84', (new Extensions\blackfire($php, $arm64, $downloader))->getBinaryArchiveURL(''));
    }

    /**
     * @return array<array{class-string<\IPE\Extension\PrecompiledExtension>, string, string, string}>
     */
    public static function provideOtherArchitectures()
    {
        return [
            [Extensions\blackfire::class, OS\Architecture::OTHER, 'riscv64', 'riscv64'],
            [Extensions\blackfire::class, OS\Architecture::ARM32, 'armv7l', 'armv7l'],
            [Extensions\relay::class, OS\Architecture::OTHER, 'riscv64', 'riscv64'],
            [Extensions\relay::class, OS\Architecture::X86, 'i686', 'i386'],
            [Extensions\sourceguardian::class, OS\Architecture::OTHER, 'riscv64', 'riscv64'],
            [Extensions\sourceguardian::class, OS\Architecture::ARM32, 'armv7l', 'armv7l'],
        ];
    }

    /**
     * @dataProvider provideOtherArchitectures
     *
     * @param class-string<\IPE\Extension\PrecompiledExtension> $class
     * @param string $architecture
     * @param string $rawArchitecture
     * @param string $expected
     *
     * @return void
     */
    public function testOtherArchitecturesAreLeftToTheVendor($class, $architecture, $rawArchitecture, $expected)
    {
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('getContent')->andReturn('1.2.3');
        $extension = new $class(self::createPHP(), self::createOS(OS\Debian::class, OS\Debian::BOOKWORM, null, $architecture, $rawArchitecture), $downloader);
        self::assertStringContainsString($expected, $extension->getBinaryArchiveURL(''));
    }

    /**
     * @return void
     */
    public function testTidewaysUnavailableArchitecture()
    {
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('getContent')->andReturn(JSON::encode(['php' => ['links' => ['linux' => ['amd64' => 'https://example.com/tideways.tar.gz']]]]));
        $os = self::createOS(OS\Debian::class, 12, null, OS\Architecture::OTHER, 'riscv64');
        $this->expectException(Exception\IO\NotFound::class);
        (new Extensions\tideways(self::createPHP(), $os, $downloader))->getBinaryArchiveURL('');
    }

    /**
     * @return void
     */
    public function testTidewaysOldPHP80()
    {
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('getContent')->andReturn(JSON::encode(['php' => ['links' => ['linux' => ['arm64' => 'https://example.com/tideways.tar.gz']]]]));
        $os = self::createOS(OS\Debian::class, 12);
        self::assertSame('https://example.com/tideways.tar.gz', (new Extensions\tideways(self::createPHP(800, false, 64, '8.0.13'), $os, $downloader))->getBinaryArchiveURL(''));
        $this->expectException(Exception\IO\NotFound::class);
        (new Extensions\tideways(self::createPHP(800, false, 64, '8.0.12'), $os, $downloader))->getBinaryArchiveURL('');
    }

    /**
     * @return void
     */
    public function testNonPosixSystem()
    {
        $os = M::mock(OS\Windows::class);
        $os->shouldReceive('getDisplayName')->andReturn('The OS');
        $this->expectException(Exception\UnsupportedOS::class);
        (new Extensions\sourceguardian(self::createPHP(), $os, M::mock(Downloader::class)))->getBinaryArchiveURL('');
    }

    /**
     * @return void
     */
    public function testSourceGuardian()
    {
        $downloader = M::mock(Downloader::class);
        $os = self::createOS(OS\Debian::class, 12);
        $extension = new Extensions\sourceguardian(self::createPHP(), $os, $downloader);
        self::assertMatchesRegularExpression('#/loaders\.linux-\w+\.tar\.gz$#', $extension->getBinaryArchiveURL(''));
        self::assertSame('/src/ixed.8.4.27.lin', (new Extensions\sourceguardian(self::createPHP(), self::createOS(OS\Debian::class, 12, self::createFilesystem(['/src/ixed.8.4.27.lin', '/src/ixed.8.4.lin'])), $downloader))->findBinaryModule('/src', ''));
        self::assertSame('/src/ixed.8.4.lin', (new Extensions\sourceguardian(self::createPHP(), self::createOS(OS\Debian::class, 12, self::createFilesystem(['/src/ixed.8.4.lin', '/src/ixed.8.4ts.lin'])), $downloader))->findBinaryModule('/src', ''));
        self::assertSame('/src/ixed.8.4ts.lin', (new Extensions\sourceguardian(self::createPHP(804, true), self::createOS(OS\Debian::class, 12, self::createFilesystem(['/src/ixed.8.4.lin', '/src/ixed.8.4ts.lin'])), $downloader))->findBinaryModule('/src', ''));
        self::assertSame('', (new Extensions\sourceguardian(self::createPHP(), self::createOS(OS\Debian::class, 12, self::createFilesystem(['/src/ixed.8.3.lin'])), $downloader))->findBinaryModule('/src', ''));
        self::assertTrue($extension->isZendExtension());
        self::assertSame([SystemDependencies\libeudev::HANDLE], $extension->getRequiredSystemDependencies('', new Values()));
        try {
            $extension->getBinaryArchiveURL('1.0');
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('The extension sourceguardian is available only', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testTideways()
    {
        $link = 'https://example.com/tideways.tar.gz';
        $links = [];
        foreach (['linux', 'linux-alpine'] as $platform) {
            foreach (['amd64', 'arm64'] as $architecture) {
                $links[$platform][$architecture] = "{$link}?{$platform}-{$architecture}";
            }
        }
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('getContent')->with('https://app.tideways.io/api/current-versions')->andReturn(JSON::encode(['php' => ['links' => $links]]));
        self::assertStringEndsWith('?linux-', substr((new Extensions\tideways(self::createPHP(), self::createOS(OS\Debian::class, 12), $downloader))->getBinaryArchiveURL(''), 0, strlen($link) + 7));
        self::assertStringContainsString('?linux-alpine-', (new Extensions\tideways(self::createPHP(), self::createOS(OS\Alpine::class, 323), $downloader))->getBinaryArchiveURL(''));
        self::assertSame('/src/dist/tideways-php-8.4.so', (new Extensions\tideways(self::createPHP(), self::createOS(OS\Debian::class, 12, self::createFilesystem(['/src/dist/tideways-php-8.4.so'])), $downloader))->findBinaryModule('/src', ''));
        self::assertSame('/src/tideways-php-alpine-8.4-zts.so', (new Extensions\tideways(self::createPHP(804, true), self::createOS(OS\Alpine::class, 323, self::createFilesystem(['/src/tideways-php-alpine-8.4-zts.so'])), $downloader))->findBinaryModule('/src', ''));
        $invalid = M::mock(Downloader::class);
        $invalid->shouldReceive('getContent')->andReturn('{}');
        $this->expectException(Exception\IO::class);
        (new Extensions\tideways(self::createPHP(), self::createOS(OS\Debian::class, 12), $invalid))->getBinaryArchiveURL('');
    }

    /**
     * @return void
     */
    public function testRelay()
    {
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('getContent')->with('https://builds.r2.relay.so/meta/latest')->andReturn("v1.2.3\n");
        self::assertMatchesRegularExpression('#/v1\.2\.3/relay-v1\.2\.3-php8\.4-debian-aarch64\+libssl3\.tar\.gz$#', (new Extensions\relay(self::createPHP(), self::createOS(OS\Debian::class, OS\Debian::BOOKWORM), $downloader))->getBinaryArchiveURL(''));
        self::assertMatchesRegularExpression('#/v2\.0/relay-v2\.0-php8\.4-debian-[\w-]+\.tar\.gz$#', (new Extensions\relay(self::createPHP(), self::createOS(OS\Debian::class, OS\Debian::BULLSEYE), $downloader))->getBinaryArchiveURL('2.0'));
        self::assertMatchesRegularExpression('#-alpine3\.9-[\w-]+\.tar\.gz$#', (new Extensions\relay(self::createPHP(), self::createOS(OS\Alpine::class, 316), $downloader))->getBinaryArchiveURL('2.0'));
        self::assertMatchesRegularExpression('#-alpine3\.17-[\w-]+\.tar\.gz$#', (new Extensions\relay(self::createPHP(), self::createOS(OS\Alpine::class, 317), $downloader))->getBinaryArchiveURL('2.0'));
        $fs = self::createFilesystem(['/src/relay-pkg.so', '/src/relay-zts.so']);
        $os = self::createOS(OS\Debian::class, 12, $fs);
        self::assertSame('/src/relay-pkg.so', (new Extensions\relay(self::createPHP(), $os, $downloader))->findBinaryModule('/src', ''));
        self::assertSame('/src/relay-zts.so', (new Extensions\relay(self::createPHP(804, true), $os, $downloader))->findBinaryModule('/src', ''));
        $extension = new Extensions\relay(self::createPHP(), $os, $downloader);
        self::assertSame([Extensions\msgpack::HANDLE, Extensions\igbinary::HANDLE], $extension->getRequiredExtensions(''));
        self::assertSame([SystemDependencies\binutils::HANDLE], $extension->getRequiredSystemDependencies('', new Values()));
        self::assertSame(
            [SystemDependencies\binutils::HANDLE, SystemDependencies\liblz4::HANDLE, SystemDependencies\libzstd::HANDLE],
            (new Extensions\relay(self::createPHP(), self::createOS(OS\Alpine::class, 316), $downloader))->getRequiredSystemDependencies('', new Values())
        );
        self::assertSame(
            [SystemDependencies\binutils::HANDLE, SystemDependencies\liblz4::HANDLE, SystemDependencies\libzstd::HANDLE, SystemDependencies\libopenssl::HANDLE],
            (new Extensions\relay(self::createPHP(), self::createOS(OS\Alpine::class, 317), $downloader))->getRequiredSystemDependencies('', new Values())
        );
        $fs = self::createFilesystem(['/src/relay.ini']);
        $fs->shouldReceive('readFile')->with('/src/relay.ini')->andReturn("; comment\n  extension = relay.so\nrelay.key = x\n");
        self::assertSame("; comment\nrelay.key = x\n", (new Extensions\relay(self::createPHP(), self::createOS(OS\Debian::class, 12, $fs), $downloader))->getBinaryIniSettings(self::createBuildContext('', null, '/src')));
    }

    /**
     * @return array<array{class-string<\IPE\OS>, int, bool, bool}>
     */
    public static function provideRelayZTSLibraries()
    {
        return [
            [OS\Debian::class, OS\Debian::TRIXIE, true, true],
            [OS\Debian::class, OS\Debian::TRIXIE, false, false],
            [OS\Debian::class, OS\Debian::BOOKWORM, false, false],
            [OS\Alpine::class, 318, true, true],
            [OS\Alpine::class, 318, false, false],
            [OS\Alpine::class, 317, false, false],
        ];
    }

    /**
     * @return array<array{class-string<\IPE\OS>, int, bool}> the OS, its version, and whether ZTS is rejected there
     */
    public static function provideRelayZTSEnvironments()
    {
        return [
            [OS\Debian::class, OS\Debian::BUSTER, true],
            [OS\Debian::class, OS\Debian::BULLSEYE, true],
            [OS\Debian::class, OS\Debian::BOOKWORM, true],
            [OS\Debian::class, OS\Debian::TRIXIE, false],
            [OS\Alpine::class, 310, true],
            [OS\Alpine::class, 316, true],
            [OS\Alpine::class, 317, true],
            [OS\Alpine::class, 318, false],
            [OS\Alpine::class, 321, false],
            [OS\Alpine::class, 322, true],
            [OS\Alpine::class, 324, true],
        ];
    }

    /**
     * @dataProvider provideRelayZTSEnvironments
     *
     * @param class-string<\IPE\OS> $class
     * @param int $version
     * @param bool $rejected
     *
     * @return void
     */
    public function testRelayZTSEnvironments($class, $version, $rejected)
    {
        $subject = new UnsupportedEnvironment(new OS\Factory(new Container(), new \IPE\Logger()));
        $os = self::createOS($class, $version);
        $matchesAny = static function (PHPInfo $php) use ($subject, $os) {
            foreach (array_keys(Extensions\relay::getUnsupportedEnvironments()) as $environment) {
                if ($subject->matches($environment, $php, $os)) {
                    return true;
                }
            }

            return false;
        };
        self::assertSame($rejected, $matchesAny(self::createPHP(804, true)));
        self::assertFalse($matchesAny(self::createPHP(804, false)));
    }

    /**
     * @dataProvider provideRelayZTSLibraries
     *
     * @param class-string<\IPE\OS> $class
     * @param int $version
     * @param bool $threadSafe
     * @param bool $expected
     *
     * @return void
     */
    public function testRelayZTSLibraries($class, $version, $threadSafe, $expected)
    {
        $extension = new Extensions\relay(self::createPHP(804, $threadSafe), self::createOS($class, $version), M::mock(Downloader::class));
        $dependencies = $extension->getRequiredSystemDependencies('', new Values());
        self::assertSame($expected, in_array(SystemDependencies\libhiredis::HANDLE, $dependencies, true));
        self::assertSame($expected, in_array(SystemDependencies\libck::HANDLE, $dependencies, true));
    }

    /**
     * @return void
     */
    public function testRelayZTSLibrariesPackages()
    {
        $php = self::createPHP(804, true);
        $state = M::mock(State::class);
        $alpine = self::createOS(OS\Alpine::class, 318);
        $debian = self::createOS(OS\Debian::class, OS\Debian::TRIXIE);
        self::assertInstanceOf(OS\Posix::class, $alpine);
        self::assertInstanceOf(OS\Posix::class, $debian);
        self::assertSame(['hiredis', 'hiredis-ssl'], (new SystemDependencies\libhiredis($php, $alpine, new Values()))->getSystemPackages($state)->getPersistent());
        self::assertSame(['libhiredis1.1.0'], (new SystemDependencies\libhiredis($php, $debian, new Values()))->getSystemPackages($state)->getPersistent());
        self::assertSame(['ck'], (new SystemDependencies\libck($php, $alpine, new Values()))->getSystemPackages($state)->getPersistent());
        self::assertSame(['^libck0(t64)?$'], (new SystemDependencies\libck($php, $debian, new Values()))->getSystemPackages($state)->getPersistent());
    }

    /**
     * @return void
     */
    public function testRelayModuleID()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('readFile')->with('/proc/sys/kernel/random/uuid')->andReturn("11111111-2222-3333-4444-555555555555\n");
        $fs->shouldReceive('readFile')->with('/ext/relay.so')->andReturn('a00000000-0000-0000-0000-000000000000b');
        $fs->shouldReceive('writeFile')->once()->with('/ext/relay.so', 'a11111111-2222-3333-4444-555555555555b');
        (new Extensions\relay(self::createPHP(), self::createOS(OS\Debian::class, 12, $fs), M::mock(Downloader::class)))->postProcessBinaryModule('/ext/relay.so');
        $this->addToAssertionCount(1);
    }

    /**
     * @return void
     */
    public function testNewRelicAvailableArchitectures()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('isDirectory')->with('/src/agent/x64')->andReturn(true);
        $fs->shouldReceive('isDirectory')->with('/src/agent/aarch64')->andReturn(true);
        $fs->shouldReceive('isDirectory')->with('/src/agent/x86')->andReturn(false);
        $fs->shouldReceive('isDirectory')->with('/src/agent/armv7l')->andReturn(false);
        $downloader = M::mock(Downloader::class);
        self::assertTrue((new Extensions\newrelic(self::createPHP(), self::createOS(OS\Debian::class, 12, $fs, OS\Architecture::X64, 'x86_64'), $downloader, new \IPE\Logger()))->canRunBinaryInstaller('/src'));
        self::assertTrue((new Extensions\newrelic(self::createPHP(), self::createOS(OS\Debian::class, 12, $fs), $downloader, new \IPE\Logger()))->canRunBinaryInstaller('/src'));
        self::assertFalse((new Extensions\newrelic(self::createPHP(804, false, 32), self::createOS(OS\Debian::class, 12, $fs, OS\Architecture::X64, 'x86_64'), $downloader, new \IPE\Logger()))->canRunBinaryInstaller('/src'));
        self::assertFalse((new Extensions\newrelic(self::createPHP(804, false, 32), self::createOS(OS\Debian::class, 12, $fs), $downloader, new \IPE\Logger()))->canRunBinaryInstaller('/src'));
    }

    /**
     * @return void
     */
    public function testNewRelic()
    {
        self::assertSame('10.21.0.11', (new Extensions\newrelic(self::createPHP(701), M::mock(OS::class), M::mock(Downloader::class), new \IPE\Logger()))->getDefaultVersionRequirements());
        self::assertSame('', (new Extensions\newrelic(self::createPHP(702), M::mock(OS::class), M::mock(Downloader::class), new \IPE\Logger()))->getDefaultVersionRequirements());
        $page = '<a href="x">newrelic-php5-12.1.0.1-linux-musl.tar.gz</a> <a href="y">newrelic-php5-12.1.0.1-linux.tar.gz</a>';
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('getContent')->with('https://download.newrelic.com/php_agent/release/')->andReturn($page);
        $downloader->shouldReceive('getContent')->with('https://download.newrelic.com/php_agent/archive/12.1.0.1/')->andReturn($page);
        self::assertSame('https://download.newrelic.com/php_agent/release/newrelic-php5-12.1.0.1-linux.tar.gz', (new Extensions\newrelic(self::createPHP(), self::createOS(OS\Debian::class, 12), $downloader, new \IPE\Logger()))->getBinaryArchiveURL(''));
        self::assertSame('https://download.newrelic.com/php_agent/archive/12.1.0.1/newrelic-php5-12.1.0.1-linux-musl.tar.gz', (new Extensions\newrelic(self::createPHP(), self::createOS(OS\Alpine::class, 323), $downloader, new \IPE\Logger()))->getBinaryArchiveURL('12.1.0.1'));
    }

    /**
     * @return void
     */
    public function testNewRelicInstaller()
    {
        $commands = [];
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('run')->andReturnUsing(static function (Command $command) use (&$commands) {
            $commands[] = $command;

            return [0, ''];
        });
        $fs->shouldReceive('listDirectoryContents')->with('/tmp')->andReturn(['/tmp/nrinstall-1.tar', '/tmp/other.tar']);
        $fs->shouldReceive('deleteFile')->once()->with('/tmp/nrinstall-1.tar');
        $fs->shouldReceive('createDirectory')->once()->with('/src/ipe-php');
        $fs->shouldReceive('createSymlink')->once()->with('/usr/local/bin/php', '/src/ipe-php/php');
        (new Extensions\newrelic(self::createPHP(), self::createOS(OS\Debian::class, 12, $fs), M::mock(Downloader::class), new \IPE\Logger()))->runBinaryInstaller(self::createBuildContext('', new Values([], [Extensions\newrelic::OPTION_INSTALL_DAEMON => true]), '/src'));
        self::assertCount(2, $commands);
        self::assertSame('./newrelic-install', $commands[0]->getCommand());
        self::assertSame(['install'], $commands[0]->getArguments());
        self::assertSame(['install_daemon'], $commands[1]->getArguments());
        self::assertSame(['NR_INSTALL_USE_CP_NOT_LN' => '1', 'NR_INSTALL_SILENT' => '1', 'NR_INSTALL_PHPLIST' => '/src/ipe-php'], $commands[0]->getEnvironment());
    }

    /**
     * @return void
     */
    public function testNewRelicInstallerFailure()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('run')->andReturn([1, '']);
        $fs->shouldReceive('createDirectory');
        $fs->shouldReceive('createSymlink');
        $this->expectException(Exception\IO::class);
        (new Extensions\newrelic(self::createPHP(), self::createOS(OS\Debian::class, 12, $fs), M::mock(Downloader::class), new \IPE\Logger()))->runBinaryInstaller(self::createBuildContext('', null, '/src'));
    }

    /**
     * @return array<array{string, string}> the ddtrace version, and the expected URL of the setup script
     */
    public static function provideDDTraceSetupURLs()
    {
        return [
            ['', 'https://github.com/DataDog/dd-trace-php/releases/latest/download/datadog-setup.php'],
            ['1.14.0', 'https://github.com/DataDog/dd-trace-php/releases/download/1.14.0/datadog-setup.php'],
        ];
    }

    /**
     * @dataProvider provideDDTraceSetupURLs
     *
     * @param string $version
     * @param string $expectedURL
     *
     * @return void
     */
    public function testDDTraceInstaller($version, $expectedURL)
    {
        $commands = [];
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('run')->andReturnUsing(static function (Command $command) use (&$commands) {
            $commands[] = $command;

            return [0, ''];
        });
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('saveAs')->once()->with($expectedURL, '/src/datadog-setup.php');
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/local/bin/php');
        $registry = M::mock(Registry::class);
        $registry->shouldReceive('getExtensionClasses')->andReturn([]);
        $ddtrace = new Extensions\ddtrace($php, self::createOS(OS\Debian::class, 12, $fs), $downloader, new PHPScript($php, $registry));
        $ddtrace->runBinaryInstaller(self::createBuildContext($version, new Values([], [Extensions\ddtrace::OPTION_PROFILING => true]), '/src'));
        self::assertCount(1, $commands);
        self::assertSame(['/src/datadog-setup.php', '--php-bin=php', '--enable-profiling'], $commands[0]->getArguments());
    }

    /**
     * @return array<array{string, bool}> the pdo_snowflake version, and whether the build script needs PLATFORM
     */
    public static function providePdoSnowflakePlatforms()
    {
        return [
            ['0.2.4', true],
            ['0.2.5', false],
            ['2.0.0', false],
        ];
    }

    /**
     * @dataProvider providePdoSnowflakePlatforms
     *
     * @param string $version
     * @param bool $needsPlatform
     *
     * @return void
     */
    public function testPdoSnowflakeBuildModule($version, $needsPlatform)
    {
        $commands = [];
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->with('/usr/local/bin/php-config')->andReturn(true);
        $fs->shouldReceive('run')->andReturnUsing(static function (Command $command) use (&$commands) {
            switch ($command->getCommand()) {
                case '/usr/local/bin/php-config':
                    return [0, "8.4.27\n"];
                case 'uname':
                    $commands[] = $command;

                    return [0, "Linux\n"];
                default:
                    $commands[] = $command;

                    return [0, ''];
            }
        });
        $fs->shouldReceive('createDirectory')->once();
        $fs->shouldReceive('copyFile')->once()->with('/src/libsnowflakeclient/cacert.pem', Extensions\pdo_snowflake::CACERT_FILE);
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/local/bin/php');
        $php->shouldReceive('getVersion')->andReturn('8.4.27');
        $pdoSnowflake = new Extensions\pdo_snowflake($php, self::createOS(OS\Debian::class, 12, $fs));
        self::assertSame('/src/modules/pdo_snowflake.so', $pdoSnowflake->buildModule(self::createBuildContext($version, null, '/src'), new BuildTools($php, $fs, new \IPE\Logger())));
        self::assertCount($needsPlatform ? 2 : 1, $commands);
        $build = $commands[count($commands) - 1];
        self::assertSame('bash', $build->getCommand());
        $expectedEnvironment = ['PHP_HOME' => '/usr/local'];
        if ($needsPlatform) {
            $expectedEnvironment['PLATFORM'] = 'linux';
        }
        self::assertSame($expectedEnvironment, $build->getEnvironment());
    }

    /**
     * @return void
     */
    public function testPECLDefaults()
    {
        self::assertSame('0.5.7', (new Extensions\msgpack(self::createPHP(506), M::mock(OS::class)))->getDefaultVersionRequirements());
        self::assertSame('', (new Extensions\msgpack(self::createPHP(700), M::mock(OS::class)))->getDefaultVersionRequirements());
        self::assertSame('2.0.8', (new Extensions\igbinary(self::createPHP(506), M::mock(OS::class)))->getDefaultVersionRequirements());
        self::assertSame('', (new Extensions\igbinary(self::createPHP(700), M::mock(OS::class)))->getDefaultVersionRequirements());
    }
}
