<?php

namespace IPE\Test\SystemDependencies;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Filesystem\Downloader;
use IPE\Filesystem\VolatileDirectory;
use IPE\Installer\State;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\PHPInfo\SSLLibrary;
use IPE\SystemDependencies\libcclient;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class LibcclientTest extends TestCase
{
    /**
     * @var list<\IPE\Filesystem\Command>
     */
    private $commands = [];

    /**
     * @param int $osVersion
     * @param string $phpVersionText the value of OPENSSL_VERSION_TEXT in PHP
     * @param list<string> $downloadedFiles what apt-get download saves
     *
     * @return \IPE\SystemDependencies\libcclient
     */
    private function createDependency($osVersion, $phpVersionText, array $downloadedFiles = [])
    {
        $fs = M::mock(Filesystem::class)->shouldIgnoreMissing();
        $fs->shouldReceive('run')->andReturnUsing(function (Command $command) {
            $this->commands[] = $command;

            return [0, ''];
        });
        $fs->shouldReceive('listDirectoryContents')->andReturn($downloadedFiles);
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getSSLLibrary')->andReturn(SSLLibrary::fromVersionText($phpVersionText));

        return new libcclient($php, $os, new Values(), M::mock(Downloader::class));
    }

    /**
     * @return \IPE\Installer\State
     */
    private static function createState()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('createVolatileDirectory')->andReturn(new VolatileDirectory('/tmp/ipe', M::mock(Filesystem::class)->shouldIgnoreMissing()));

        return new State(M::mock(PHPInfo::class), $fs);
    }

    /**
     * @return array<array{int, string, list<string>, list<string>}>
     */
    public static function provideSystemPackagesCases()
    {
        return [
            [OS\Debian::STRETCH, 'OpenSSL 1.0.2q  20 Nov 2018', ['libc-client2007e'], ['libkrb5-dev', 'libpam0g-dev']],
            [OS\Debian::STRETCH, 'OpenSSL 1.1.0j  20 Nov 2018', ['libc-client2007e'], ['libkrb5-dev', 'libc-client-dev']],
            [OS\Debian::BOOKWORM, 'OpenSSL 3.0.15 3 Sep 2024', ['libc-client2007e'], ['libkrb5-dev', 'libc-client-dev']],
        ];
    }

    /**
     * @dataProvider provideSystemPackagesCases
     *
     * @param int $osVersion
     * @param string $phpVersionText
     * @param list<string> $persistent
     * @param list<string> $volatile
     *
     * @return void
     */
    public function testSystemPackages($osVersion, $phpVersionText, array $persistent, array $volatile)
    {
        $packages = $this->createDependency($osVersion, $phpVersionText)->getSystemPackages(M::mock(State::class));
        self::assertSame($persistent, $packages->getPersistent());
        self::assertSame($volatile, $packages->getVolatile());
    }

    /**
     * @return void
     */
    public function testStretchWithOpenSSL10IgnoresLibsslDev()
    {
        $dependency = $this->createDependency(OS\Debian::STRETCH, 'OpenSSL 1.0.2q  20 Nov 2018', ['libc-client2007e-dev_8%3a2007f~dfsg-5+deb9u1_amd64.deb']);
        $state = self::createState();
        $dependency->install($state);
        $dependency->finalize($state);
        $state->shutdown(new \IPE\Logger());
        $commands = $this->describeCommands();
        self::assertCount(3, $commands);
        $dir = $commands[0][2];
        self::assertNotSame('', $dir);
        self::assertSame(
            [
                ['apt-get', ['download', 'libc-client2007e-dev'], $dir],
                ['dpkg', ['-i', '--ignore-depends=libssl-dev', "{$dir}/libc-client2007e-dev_8%3a2007f~dfsg-5+deb9u1_amd64.deb"], ''],
                ['dpkg', ['-r', 'libc-client2007e-dev'], ''],
            ],
            $commands
        );
    }

    /**
     * @return void
     */
    public function testDevPackageRemovedAtShutdownWithoutFinalize()
    {
        $dependency = $this->createDependency(OS\Debian::STRETCH, 'OpenSSL 1.0.2q  20 Nov 2018', ['libc-client2007e-dev_8%3a2007f~dfsg-5+deb9u1_amd64.deb']);
        $state = self::createState();
        $dependency->install($state);
        $state->shutdown(new \IPE\Logger());
        $state->shutdown(new \IPE\Logger());
        $commands = $this->describeCommands();
        self::assertCount(3, $commands);
        self::assertSame(['dpkg', ['-r', 'libc-client2007e-dev'], ''], $commands[2]);
    }

    /**
     * @return void
     */
    public function testStretchWithOpenSSL10FailsWithoutDownloadedPackage()
    {
        $dependency = $this->createDependency(OS\Debian::STRETCH, 'OpenSSL 1.0.2q  20 Nov 2018');
        $this->expectException(Exception\IO::class);
        $this->expectExceptionMessage('Failed to download libc-client2007e-dev');
        $dependency->install(self::createState());
    }

    /**
     * @return void
     */
    public function testStretchWithOpenSSL11UsesAPT()
    {
        $dependency = $this->createDependency(OS\Debian::STRETCH, 'OpenSSL 1.1.0j  20 Nov 2018');
        $dependency->install(self::createState());
        $dependency->finalize(self::createState());
        self::assertSame([], $this->describeCommands());
    }

    /**
     * @return list<array{string, list<string>, string}> the executable, the arguments and the working directory of the commands that have been run
     */
    private function describeCommands()
    {
        return array_map(
            static function (Command $command) {
                return [$command->getCommand(), $command->getArguments(), $command->getWorkingDirectory()];
            },
            $this->commands
        );
    }
}
