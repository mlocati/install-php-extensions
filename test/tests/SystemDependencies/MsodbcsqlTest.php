<?php

namespace IPE\Test\SystemDependencies;

use IPE\Exception;
use IPE\Extensions\pdo_sqlsrv;
use IPE\Extensions\sqlsrv;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Filesystem\Downloader;
use IPE\Filesystem\VolatileDirectory;
use IPE\Installer\State;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\msodbcsql;
use IPE\SystemPackager;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class MsodbcsqlTest extends TestCase
{
    /**
     * @var list<\IPE\Filesystem\Command>
     */
    private $commands = [];

    /**
     * @var array<string, string>
     */
    private $writtenFiles = [];

    /**
     * @var list<string>
     */
    private $downloads = [];

    /**
     * @param class-string<\IPE\OS\Posix> $osClass
     * @param int $osVersion
     * @param int $phpVersion
     * @param string $odbcinst the content of /etc/odbcinst.ini (empty string if it doesn't exist)
     * @param string $architecture
     * @param string $rawArchitecture
     * @param int $bits
     *
     * @return \IPE\SystemDependencies\msodbcsql
     */
    private function createDependency($osClass, $osVersion, $phpVersion, $odbcinst = '', $architecture = OS\Architecture::X64, $rawArchitecture = 'x86_64', $bits = 64)
    {
        $fs = M::mock(Filesystem::class)->shouldIgnoreMissing();
        $fs->shouldReceive('isFile')->with('/etc/odbcinst.ini')->andReturn($odbcinst !== '');
        $fs->shouldReceive('readFile')->with('/etc/odbcinst.ini')->andReturn($odbcinst);
        $fs->shouldReceive('writeFile')->andReturnUsing(function ($path, $content) {
            if (is_string($path) && is_string($content)) {
                $this->writtenFiles[$path] = $content;
            }
        });
        $fs->shouldReceive('run')->andReturnUsing(function (Command $command) {
            $this->commands[] = $command;

            return [0, ''];
        });
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
        $php->shouldReceive('getBits')->andReturn($bits);
        $os = M::mock($osClass);
        self::assertInstanceOf(OS\Posix::class, $os);
        $os->shouldReceive('getMachineArchitecture')->andReturn($architecture);
        $os->shouldReceive('getRawMachineArchitecture')->andReturn($rawArchitecture);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getDisplayName')->andReturn('The OS');
        $os->shouldReceive('getCodename')->andReturn('thecodename');
        $packager = M::mock(SystemPackager::class);
        $packager->shouldReceive('refresh');
        $os->shouldReceive('getSystemPackager')->andReturn($packager);
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('saveAs')->andReturnUsing(function ($url) {
            if (is_string($url)) {
                $this->downloads[] = $url;
                if (substr($url, -4) === '.apk' && !preg_match('/_amd64\.apk$|msodbcsql18_.*_arm64\.apk$/', $url)) {
                    throw new Exception\IO\NotFound("{$url} not found");
                }
            }
        });

        return new msodbcsql($php, $os, new Values(), $downloader, new \IPE\Logger());
    }

    /**
     * @return \IPE\Installer\State&\Mockery\MockInterface
     */
    private static function createState()
    {
        $state = M::mock(State::class);
        $state->shouldReceive('getVolatileDir')->andReturn(new VolatileDirectory('/tmp/ipe', M::mock(Filesystem::class)->shouldIgnoreMissing()));

        return $state;
    }

    /**
     * @return void
     */
    public function testSystemPackages()
    {
        $alpine = $this->createDependency(OS\Alpine::class, 323, 804)->getSystemPackages(M::mock(State::class));
        self::assertSame(['libstdc++', 'unixodbc'], $alpine->getPersistent());
        self::assertSame(['unixodbc-dev'], $alpine->getVolatile());
        $debian = $this->createDependency(OS\Debian::class, OS\Debian::VERSION_BUSTER, 804)->getSystemPackages(M::mock(State::class));
        self::assertSame(['unixodbc'], $debian->getPersistent());
        self::assertSame(['unixodbc-dev'], $debian->getVolatile());
        $old = $this->createDependency(OS\Debian::class, OS\Debian::VERSION_STRETCH, 700)->getSystemPackages(M::mock(State::class));
        self::assertSame(['unixodbc', 'apt-transport-https'], $old->getPersistent());
        self::assertSame(['unixodbc-dev'], $old->getVolatile());
    }

    /**
     * @return void
     */
    public function testUnsupportedOS()
    {
        $this->expectException(Exception\UnsupportedOS::class);
        $this->createDependency(OS\Ubuntu::class, 2404, 804)->getSystemPackages(M::mock(State::class));
    }

    /**
     * @return void
     */
    public function testInstallOnUnsupportedOS()
    {
        $this->expectException(Exception\UnsupportedOS::class);
        $this->createDependency(OS\Ubuntu::class, 2404, 804)->install(M::mock(State::class));
    }

    /**
     * @return void
     */
    public function testAlreadyInstalled()
    {
        $this->createDependency(OS\Debian::class, OS\Debian::VERSION_TRIXIE, 804, "[Other]\nDriver=x\n\n[ODBC Driver 18 for SQL Server]\nDriver=y\n")->install(M::mock(State::class));
        self::assertSame([], $this->commands);
        self::assertSame([], $this->downloads);
    }

    /**
     * @return list<array{int, int, string, string}>
     */
    public static function provideDebianCases()
    {
        return [
            [OS\Debian::VERSION_TRIXIE, 804, 'microsoft-2025.asc', 'msodbcsql18'],
            [OS\Debian::VERSION_BOOKWORM, 804, 'microsoft.asc', 'msodbcsql18'],
            [OS\Debian::VERSION_BUSTER, 703, 'microsoft.asc', 'msodbcsql17'],
            [OS\Debian::VERSION_FORKY, 804, 'microsoft-2025.asc', '^msodbcsql[0-9]+$'],
        ];
    }

    /**
     * @dataProvider provideDebianCases
     *
     * @param int $osVersion
     * @param int $phpVersion
     * @param string $expectedKey
     * @param string $expectedPackage
     *
     * @return void
     */
    public function testInstallOnDebian($osVersion, $phpVersion, $expectedKey, $expectedPackage)
    {
        $this->createDependency(OS\Debian::class, $osVersion, $phpVersion)->install(self::createState());
        self::assertSame(["https://packages.microsoft.com/keys/{$expectedKey}"], $this->downloads);
        self::assertSame(["deb [signed-by=/usr/share/keyrings/microsoft-prod.asc] https://packages.microsoft.com/debian/{$osVersion}/prod thecodename main\n"], array_values($this->writtenFiles));
        self::assertCount(1, $this->commands);
        self::assertSame('apt-get', $this->commands[0]->getCommand());
        self::assertSame(['install', '-qy', '--no-install-recommends', $expectedPackage], $this->commands[0]->getArguments());
        self::assertSame(['ACCEPT_EULA' => 'Y'], $this->commands[0]->getEnvironment());
    }

    /**
     * @return void
     */
    public function testInstallOnDebian8()
    {
        $this->createDependency(OS\Debian::class, OS\Debian::VERSION_JESSIE, 700)->install(self::createState());
        self::assertSame(['https://packages.microsoft.com/keys/microsoft.asc'], $this->downloads);
        self::assertSame(["deb https://packages.microsoft.com/debian/8/prod thecodename main\n"], array_values($this->writtenFiles));
        self::assertCount(2, $this->commands);
        self::assertSame('apt-key', $this->commands[0]->getCommand());
        self::assertSame('apt-get', $this->commands[1]->getCommand());
    }

    /**
     * @return list<array{int, int, string}>
     */
    public static function provideAlpineCases()
    {
        return [
            [310, 703, 'e/4/e/e4e67866-dffd-428c-aac7-8d28ddafb39b/msodbcsql17_17.10.6.1-1'],
            [315, 804, '3/5/5/355d7943-a338-41a7-858d-53b259ea33f5/msodbcsql18_18.3.3.1-1'],
            [316, 703, '607ebe2c-e17c-4c34-b367-10a75b83bef9/msodbcsql17_17.11.1.1-1'],
            [316, 804, 'ade174b7-8cea-4543-91a6-c33ae320c2f0/msodbcsql18_18.7.1.1-1'],
        ];
    }

    /**
     * @dataProvider provideAlpineCases
     *
     * @param int $osVersion
     * @param int $phpVersion
     * @param string $expectedPackage
     *
     * @return void
     */
    public function testInstallOnAlpine($osVersion, $phpVersion, $expectedPackage)
    {
        $this->createDependency(OS\Alpine::class, $osVersion, $phpVersion)->install(self::createState());
        self::assertSame(["https://download.microsoft.com/download/{$expectedPackage}_amd64.apk"], $this->downloads);
        self::assertCount(1, $this->commands);
        self::assertSame('apk', $this->commands[0]->getCommand());
        self::assertSame(['add', '--allow-untrusted'], array_slice($this->commands[0]->getArguments(), 0, 2));
        self::assertStringEndsWith('/msodbcsql.apk', $this->commands[0]->getArguments()[2]);
    }

    /**
     * @return void
     */
    public function testFailure()
    {
        $fs = M::mock(Filesystem::class)->shouldIgnoreMissing();
        $fs->shouldReceive('run')->andReturn([1, '']);
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(804);
        $php->shouldReceive('getBits')->andReturn(64);
        $os = M::mock(OS\Alpine::class);
        $os->shouldReceive('getMachineArchitecture')->andReturn(OS\Architecture::X64);
        $os->shouldReceive('getNumericVersion')->andReturn(323);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('saveAs');
        $this->expectException(Exception\SystemPackager::class);
        (new msodbcsql($php, $os, new Values(), $downloader, new \IPE\Logger()))->install(self::createState());
    }

    /**
     * @return void
     */
    public function testAlpineArchitectures()
    {
        $this->createDependency(OS\Alpine::class, 323, 804)->install(self::createState());
        self::assertStringEndsWith('_amd64.apk', $this->downloads[0]);
        $this->createDependency(OS\Alpine::class, 323, 804, '', OS\Architecture::ARM64, 'aarch64')->install(self::createState());
        self::assertStringEndsWith('_arm64.apk', $this->downloads[1]);
    }

    /**
     * @return array<array{int, int, string, string, string, string}>
     */
    public static function provideUnavailableAlpineArchitectures()
    {
        return [
            [703, 64, OS\Architecture::ARM64, 'aarch64', 'arm64', 'aarch64'],
            [804, 32, OS\Architecture::ARM32, 'armv7l', 'armv7l', '32-bit PHP on armv7l'],
            [804, 32, OS\Architecture::X64, 'x86_64', 'i386', '32-bit PHP on x86_64'],
            [804, 64, OS\Architecture::OTHER, 'riscv64', 'riscv64', 'riscv64'],
        ];
    }

    /**
     * @dataProvider provideUnavailableAlpineArchitectures
     *
     * @param int $phpVersion
     * @param int $bits
     * @param string $architecture
     * @param string $rawArchitecture
     * @param string $vendorName
     * @param string $description
     *
     * @return void
     */
    public function testUnavailableAlpineArchitectures($phpVersion, $bits, $architecture, $rawArchitecture, $vendorName, $description)
    {
        $dependency = $this->createDependency(OS\Alpine::class, 323, $phpVersion, '', $architecture, $rawArchitecture, $bits);
        try {
            $dependency->install(self::createState());
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedOS $x) {
            self::assertStringContainsString("not available for {$description}", $x->getMessage());
        }
        self::assertStringEndsWith("_{$vendorName}.apk", $this->downloads[0]);
        self::assertSame([], $this->commands);
    }

    /**
     * @return list<array{int, string}>
     */
    public static function provideDefaultVersions()
    {
        return [
            [700, '5.3.0'],
            [701, '5.6.1'],
            [702, '5.8.1'],
            [703, '5.9.0'],
            [704, '5.10.1'],
            [800, '5.11.1'],
            [801, '5.12.0'],
            [802, '5.12.0'],
            [803, ''],
            [805, ''],
        ];
    }

    /**
     * @dataProvider provideDefaultVersions
     *
     * @param int $phpVersion
     * @param string $expected
     *
     * @return void
     */
    public function testExtensions($phpVersion, $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
        $os = M::mock(OS::class);
        foreach ([new sqlsrv($php, $os), new pdo_sqlsrv($php, $os)] as $extension) {
            self::assertSame($expected, $extension->getDefaultVersionRequirements());
            self::assertSame([msodbcsql::HANDLE], $extension->getRequiredSystemDependencies('', new Values()));
            self::assertSame([$extension::getHandle()], $extension::getInstalledNames());
            self::assertSame($extension::getHandle(), $extension::getPECLHandle());
        }
    }

    /**
     * @return void
     */
    public function testDefaultVersionOnOldPHPOnAlpine()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(701);
        foreach ([sqlsrv::class, pdo_sqlsrv::class] as $class) {
            self::assertSame('5.8.1', (new $class($php, M::mock(OS\Alpine::class)))->getDefaultVersionRequirements());
            self::assertSame('5.6.1', (new $class($php, M::mock(OS\Debian::class)))->getDefaultVersionRequirements());
        }
    }
}
