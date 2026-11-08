<?php

namespace IPE\Test\SystemDependencies;

use IPE\Exception;
use IPE\Extensions\oci8;
use IPE\Extensions\pdo_oci;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Filesystem\Downloader;
use IPE\Filesystem\VolatileDirectory;
use IPE\Installer\State;
use IPE\Option\ConfigureOption;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\oracleinstantclient;
use IPE\SystemDependencies\unzip;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class OracleinstantclientTest extends TestCase
{
    /**
     * @public
     * @var string
     */
    const LIBRARY_PATH = '/usr/lib/oracle/21.1/client64/lib';

    /**
     * @var list<string>
     */
    private $commands = [];

    /**
     * @var list<string>
     */
    private $downloads = [];

    /**
     * @var array<string, string>
     */
    private $writtenFiles = [];

    /**
     * @var array<string, string>
     */
    private $symlinks = [];

    /**
     * @var list<string>
     */
    private $deletedFiles = [];

    /**
     * @param int $bits
     * @param int $majorMinorVersion
     *
     * @return \IPE\PHPInfo
     */
    private static function createPHP($bits = 64, $majorMinorVersion = 804)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getBits')->andReturn($bits);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($majorMinorVersion);

        return $php;
    }

    /**
     * @param class-string<\IPE\OS\Posix> $class
     * @param int $version
     * @param string $architecture
     * @param \IPE\Filesystem|null $fs
     *
     * @return \IPE\OS\Posix&\Mockery\MockInterface
     */
    private static function createOS($class, $version = 0, $architecture = OS\Architecture::X64, $fs = null)
    {
        $os = M::mock($class);
        self::assertInstanceOf(OS\Posix::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($version);
        $os->shouldReceive('getMachineArchitecture')->andReturn($architecture);
        $os->shouldReceive('getDisplayName')->andReturn('The OS');
        if ($fs !== null) {
            $os->shouldReceive('getFilesystem')->andReturn($fs);
        }

        return $os;
    }

    /**
     * @param list<string> $existing the paths that already exist
     * @param array<string, string> $files the content of the files that can be read
     *
     * @return \IPE\Filesystem
     */
    private function createFilesystem(array $existing = [], array $files = [])
    {
        $check = static function ($path) use ($existing) {
            return in_array($path, $existing, true);
        };
        $fs = M::mock(Filesystem::class)->shouldIgnoreMissing();
        $fs->shouldReceive('exists')->andReturnUsing($check);
        $fs->shouldReceive('isFile')->andReturnUsing($check);
        $fs->shouldReceive('isDirectory')->andReturnUsing($check);
        $fs->shouldReceive('isSymlink')->andReturn(false);
        $fs->shouldReceive('listDirectoryContents')->with('/lib')->andReturn(['/lib/apk', '/lib/ld-musl-x86_64.so.1']);
        $fs->shouldReceive('readFile')->andReturnUsing(static function ($path) use ($files) {
            return is_string($path) ? $files[$path] : '';
        });
        $fs->shouldReceive('writeFile')->andReturnUsing(function ($path, $content) {
            if (is_string($path) && is_string($content)) {
                $this->writtenFiles[$path] = $content;
            }
        });
        $fs->shouldReceive('createSymlink')->andReturnUsing(function ($target, $link) {
            if (is_string($target) && is_string($link)) {
                $this->symlinks[$link] = $target;
            }
        });
        $fs->shouldReceive('deleteFile')->andReturnUsing(function ($path) {
            if (is_string($path)) {
                $this->deletedFiles[] = $path;
            }
        });
        $fs->shouldReceive('run')->andReturnUsing(function (Command $command) {
            $this->commands[] = $command->getCommand();
            if ($command->getCommand() === 'gcc') {
                return [0, "x86_64-linux-gnu\n"];
            }

            return [0, ''];
        });

        return $fs;
    }

    /**
     * @param bool $basic
     *
     * @return \IPE\SystemDependencies\oracleinstantclient
     */
    private function createDependency(OS\Posix $os, $basic = false)
    {
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('saveAs')->andReturnUsing(function ($url) {
            if (is_string($url)) {
                $this->downloads[] = $url;
            }
        });
        $extractor = M::mock(Filesystem\ArchiveExtractor::class);
        $extractor->shouldReceive('extractTo')->andReturn('/extracted');

        return new oracleinstantclient(self::createPHP(), $os, new Values([], $basic ? [oracleinstantclient::OPTION_BASIC => true] : []), $downloader, $extractor, new \IPE\Logger());
    }

    /**
     * @return \IPE\Installer\State
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
        $state = self::createState();
        self::assertSame(['libaio', 'libc6-compat', 'libnsl'], $this->createDependency(self::createOS(OS\Alpine::class))->getSystemPackages($state)->getPersistent());
        self::assertSame(['libaio1t64'], $this->createDependency(self::createOS(OS\Debian::class, OS\Debian::VERSION_TRIXIE))->getSystemPackages($state)->getPersistent());
        self::assertSame(['^libaio[0-9]*$'], $this->createDependency(self::createOS(OS\Debian::class, OS\Debian::VERSION_TRIXIE - 1))->getSystemPackages($state)->getPersistent());
        self::assertSame([unzip::HANDLE], $this->createDependency(self::createOS(OS\Debian::class))->getRequiredDependencies());
        $this->expectException(Exception\UnsupportedOS::class);
        $this->createDependency(M::mock(OS\Posix::class)->shouldIgnoreMissing())->getSystemPackages($state);
    }

    /**
     * @return void
     */
    public function testUnzipOnOldAlpine()
    {
        $state = self::createState();
        $php = self::createPHP();
        self::assertSame(['libarchive-tools'], (new unzip($php, self::createOS(OS\Alpine::class, 307), new Values()))->getSystemPackages($state)->getVolatile());
        self::assertTrue((new unzip($php, self::createOS(OS\Alpine::class, 308), new Values()))->getSystemPackages($state)->isEmpty());
    }

    /**
     * @return void
     */
    public function testLibraryPath()
    {
        self::assertSame(self::LIBRARY_PATH, oracleinstantclient::getLibraryPath(self::createPHP(), self::createOS(OS\Debian::class)));
        self::assertSame('/usr/lib/oracle/19.10/client64/lib', oracleinstantclient::getLibraryPath(self::createPHP(), self::createOS(OS\Debian::class, 0, OS\Architecture::ARM64)));
        self::assertSame('/usr/lib/oracle/19.9/client/lib', oracleinstantclient::getLibraryPath(self::createPHP(32), self::createOS(OS\Debian::class)));
    }

    /**
     * @return void
     */
    public function testUnsupportedArchitecture()
    {
        $os = self::createOS(OS\Debian::class, 0, OS\Architecture::OTHER);
        $os->shouldReceive('getRawMachineArchitecture')->andReturn('riscv64');
        self::assertSame('/usr/lib/oracle/19.9/client/lib', oracleinstantclient::getLibraryPath(self::createPHP(32), self::createOS(OS\Debian::class, 0, OS\Architecture::X64)));
        $this->expectException(Exception\UnsupportedOS::class);
        $this->expectExceptionMessage('not available for riscv64');
        oracleinstantclient::getLibraryPath(self::createPHP(), $os);
    }

    /**
     * @return void
     */
    public function testInstallOnDebian()
    {
        $directory = '/usr/lib/x86_64-linux-gnu';
        $os = self::createOS(OS\Debian::class, OS\Debian::VERSION_TRIXIE, OS\Architecture::X64, $this->createFilesystem(["{$directory}/libaio.so.1t64"]));
        $dependency = $this->createDependency($os);
        $state = self::createState();
        $dependency->install($state);
        $base = 'https://download.oracle.com/otn_software/linux/instantclient/211000/';
        self::assertSame([
            "{$base}instantclient-basiclite-linux.x64-21.1.0.0.0.zip",
            "{$base}instantclient-sdk-linux.x64-21.1.0.0.0.zip",
        ], $this->downloads);
        self::assertSame(['gcc', 'mv', 'ldconfig'], $this->commands);
        self::assertSame([
            "{$directory}/libaio.so.1" => "{$directory}/libaio.so.1t64",
            self::LIBRARY_PATH . '/sdk' => '/extracted/sdk',
        ], $this->symlinks);
        self::assertSame(['/etc/ld.so.conf.d/oracle-instantclient.conf' => self::LIBRARY_PATH . "\n"], $this->writtenFiles);
        $this->deletedFiles = [];
        $dependency->finalize($state);
        self::assertSame([self::LIBRARY_PATH . '/sdk'], $this->deletedFiles);
    }

    /**
     * @return void
     */
    public function testBasicPackage()
    {
        $os = self::createOS(OS\Debian::class, OS\Debian::VERSION_TRIXIE - 1, OS\Architecture::X64, $this->createFilesystem());
        $this->createDependency($os, true)->install(self::createState());
        self::assertStringContainsString('/instantclient-basic-linux.x64-', $this->downloads[0]);
        self::assertNotContains('gcc', $this->commands);
    }

    /**
     * @return void
     */
    public function testAlreadyInstalled()
    {
        $configuration = '/etc/ld.so.conf.d/oracle-instantclient.conf';
        $os = self::createOS(OS\Debian::class, 0, OS\Architecture::X64, $this->createFilesystem([self::LIBRARY_PATH, self::LIBRARY_PATH . '/sdk', $configuration]));
        $dependency = $this->createDependency($os);
        $state = self::createState();
        $dependency->install($state);
        $dependency->finalize($state);
        self::assertSame([], $this->downloads);
        self::assertSame([], $this->commands);
        self::assertSame([], $this->writtenFiles);
        self::assertSame([], $this->deletedFiles);
    }

    /**
     * @return void
     */
    public function testAlpineLoaderWithoutPathFile()
    {
        $os = self::createOS(OS\Alpine::class, 322, OS\Architecture::X64, $this->createFilesystem(['/lib/libc.so.6']));
        $this->createDependency($os)->install(self::createState());
        self::assertSame(['/etc/ld-musl-x86_64.path' => '/lib64:/lib:/usr/local/lib:/usr/lib:' . self::LIBRARY_PATH . "\n"], $this->writtenFiles);
        self::assertSame('/lib/libc.so.6', $this->symlinks['/usr/lib/libresolv.so.2']);
    }

    /**
     * @return void
     */
    public function testAlpineLoaderWithPathFile()
    {
        $file = '/etc/ld-musl-x86_64.path';
        $os = self::createOS(OS\Alpine::class, 322, OS\Architecture::X64, $this->createFilesystem([$file], [$file => "/lib:/usr/lib\n"]));
        $this->createDependency($os)->install(self::createState());
        self::assertSame([$file => '/lib:/usr/lib:' . self::LIBRARY_PATH . "\n"], $this->writtenFiles);
        $this->writtenFiles = [];
        $os = self::createOS(OS\Alpine::class, 322, OS\Architecture::X64, $this->createFilesystem([$file], [$file => '/lib:' . self::LIBRARY_PATH . "\n"]));
        $this->createDependency($os)->install(self::createState());
        self::assertSame([], $this->writtenFiles);
    }

    /**
     * @return void
     */
    public function testPdoOciConfigurationDependsOnPHP()
    {
        $os = self::createOS(OS\Debian::class);
        $options = new Values();
        self::assertSame(['--with-pdo-oci=instantclient,' . self::LIBRARY_PATH . ',12.1'], (new pdo_oci(self::createPHP(64, 506), $os))->getConfigureArguments(self::createBuildContext('', $options)));
        self::assertSame(['--with-pdo-oci=instantclient,' . self::LIBRARY_PATH], (new pdo_oci(self::createPHP(64, 700), $os))->getConfigureArguments(self::createBuildContext('', $options)));
        self::assertSame(['--with-oci8=instantclient,' . self::LIBRARY_PATH], (new oci8(self::createPHP(64, 506), $os))->getConfigureArguments(self::createBuildContext('', $options)));
    }

    /**
     * @return void
     */
    public function testExtensionConfiguration()
    {
        $php = self::createPHP();
        $os = self::createOS(OS\Debian::class);
        $options = new Values();
        self::assertSame(['--with-oci8=instantclient,' . self::LIBRARY_PATH], (new oci8($php, $os))->getConfigureArguments(self::createBuildContext('', $options)));
        self::assertSame(['--with-pdo-oci=instantclient,' . self::LIBRARY_PATH], (new pdo_oci($php, $os))->getConfigureArguments(self::createBuildContext('', $options)));
        foreach ([[oci8::getOptions(), 'with-oci8'], [pdo_oci::getOptions(), 'with-pdo-oci']] as list($declared, $name)) {
            self::assertCount(1, $declared);
            self::assertInstanceOf(ConfigureOption::class, $declared[0]);
            self::assertSame($name, $declared[0]->getName());
            // A default would be passed to configure after the path of the client
            self::assertFalse($declared[0]->hasDefault());
        }
        $this->expectException(Exception\UnsupportedOS::class);
        (new oci8($php, M::mock(OS\Windows::class)->shouldIgnoreMissing()))->getConfigureArguments(self::createBuildContext('', $options));
    }
}
