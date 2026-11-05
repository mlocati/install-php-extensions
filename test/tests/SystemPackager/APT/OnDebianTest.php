<?php

namespace IPE\Test\SystemPackager\APT;

use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\SystemPackager\APT\OnDebian;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class OnDebianTest extends TestCase
{
    /**
     * @param int $version
     * @param string $codename
     * @param \IPE\Filesystem&\Mockery\MockInterface $fs
     *
     * @return \IPE\OS\Debian&\Mockery\MockInterface
     */
    private static function createOS($version, $codename, $fs)
    {
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getNumericVersion')->andReturn($version);
        $os->shouldReceive('getCodename')->andReturn($codename);
        $os->shouldReceive('getFilesystem')->andReturn($fs);

        return $os;
    }

    /**
     * A filesystem where apt-get update succeeds and the messages shown with raw commands are swallowed.
     *
     * @param string $sourcesList the current content of /etc/apt/sources.list
     * @param string $expectedSourcesList
     * @param \IPE\Filesystem\Command|null $aptUpdate the expected apt-get update command (null for the default one)
     *
     * @return \IPE\Filesystem&\Mockery\MockInterface
     */
    private static function createFilesystem($sourcesList, $expectedSourcesList, $aptUpdate = null)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('readFile')->with('/etc/apt/sources.list')->andReturn($sourcesList);
        $fs->shouldReceive('writeFile')->once()->with('/etc/apt/sources.list', $expectedSourcesList);
        $fs->shouldReceive('escapeShellArg')->with('/etc/apt/sources.list')->andReturn('/etc/apt/sources.list');
        $fs->shouldReceive('run')->with(M::on(static function (Command $command) {
            return $command->isRaw() && $command->getOutput() === Command::OUTPUT_SHOW;
        }))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->with(self::isCommand($aptUpdate === null ? Command::create('apt-get', ['update', '-qy']) : $aptUpdate))->andReturn([0, 'Reading package lists...']);

        return $fs;
    }

    /**
     * @return array<array{int, bool}>
     */
    public static function provideMaxOpenFilesCases()
    {
        return [
            [OS\Debian::VERSION_JESSIE, true],
            [OS\Debian::VERSION_STRETCH, false],
        ];
    }

    /**
     * @dataProvider provideMaxOpenFilesCases
     *
     * @param int $version
     * @param bool $expectLimit
     *
     * @return void
     */
    public function testMaxOpenFiles($version, $expectLimit)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('escapeShellArg')->andReturnUsing(static function ($argument) { return is_string($argument) ? "'{$argument}'" : ''; });
        $commands = [];
        $fs->shouldReceive('run')->andReturnUsing(static function (Command $command) use (&$commands) {
            $commands[] = $command;

            return [0, ''];
        });
        (new OnDebian(self::createOS($version, 'codename', $fs)))->refresh();
        self::assertCount(1, $commands);
        self::assertSame(Command::OUTPUT_SHOW, $commands[0]->getOutput());
        if ($expectLimit) {
            self::assertTrue($commands[0]->isRaw());
            self::assertSame('if [ "$(ulimit -n)" -gt 10000 2>/dev/null ]; then ulimit -n 10000; fi; exec apt-get \'update\' \'-qy\'', $commands[0]->getCommand());
        } else {
            self::assertFalse($commands[0]->isRaw());
            self::assertSame('apt-get', $commands[0]->getCommand());
        }
    }

    /**
     * @return void
     */
    public function testJessie()
    {
        $fs = self::createFilesystem(
            "deb http://deb.debian.org/debian jessie main\ndeb http://security.debian.org jessie/updates main\ndeb http://deb.debian.org/debian jessie-updates main\n",
            "deb http://archive.debian.org/debian jessie main\ndeb http://archive.debian.org/debian-security jessie/updates main\n#deb http://deb.debian.org/debian jessie-updates main\n",
            Command::raw('if [ "$(ulimit -n)" -gt 10000 2>/dev/null ]; then ulimit -n 10000; fi; exec apt-get update -qy')
        );
        $fs->shouldReceive('escapeShellArg')->with('update')->andReturn('update');
        $fs->shouldReceive('escapeShellArg')->with('-qy')->andReturn('-qy');
        $fs->shouldNotReceive('writeFile')->with('/etc/apt/apt.conf.d/99-ipe-no-check-valid-until', M::any());
        $packager = new OnDebian(self::createOS(OS\Debian::VERSION_JESSIE, 'jessie', $fs), ['fixArchived' => true]);
        $packager->refresh();
    }

    /**
     * @return void
     */
    public function testBullseye()
    {
        $fs = self::createFilesystem(
            "deb http://deb.debian.org/debian bullseye main\ndeb http://deb.debian.org/debian-security bullseye-security main\ndeb http://deb.debian.org/debian bullseye-updates main\n",
            "deb http://archive.debian.org/debian bullseye main\ndeb http://snapshot.debian.org/archive/debian-security/20260903T220410Z bullseye-security main\ndeb http://archive.debian.org/debian bullseye-updates main\n"
        );
        $fs->shouldReceive('writeFile')->once()->with('/etc/apt/apt.conf.d/99-ipe-no-check-valid-until', "Acquire::Check-Valid-Until \"false\";\n");
        $packager = new OnDebian(self::createOS(OS\Debian::VERSION_BULLSEYE, 'bullseye', $fs), ['fixArchived' => true]);
        $packager->refresh();
    }

    /**
     * @return void
     */
    public function testBullseyeWithCustomSecurityUrl()
    {
        $fs = self::createFilesystem(
            "deb http://deb.debian.org/debian-security bullseye-security main\n",
            "deb http://mirror.example.com/debian-security bullseye-security main\n"
        );
        $fs->shouldReceive('writeFile')->once()->with('/etc/apt/apt.conf.d/99-ipe-no-check-valid-until', M::type('string'));
        $packager = new OnDebian(self::createOS(OS\Debian::VERSION_BULLSEYE, 'bullseye', $fs), ['fixArchived' => true, 'archiveUrls' => ['security' => 'http://mirror.example.com/debian-security']]);
        $packager->refresh();
    }

    /**
     * @return void
     */
    public function testDisabledSecurityAndUpdates()
    {
        $fs = self::createFilesystem(
            "deb http://deb.debian.org/debian buster main\ndeb http://security.debian.org/debian-security buster/updates main\ndeb http://deb.debian.org/debian buster-updates main\n",
            "deb http://archive.debian.org/debian buster main\n#deb http://security.debian.org/debian-security buster/updates main\n#deb http://deb.debian.org/debian buster-updates main\n"
        );
        $fs->shouldReceive('exists')->with('/etc/apt/sources.list.d/buster.list')->andReturn(false);
        $packager = new OnDebian(self::createOS(OS\Debian::VERSION_BUSTER, 'buster', $fs), ['fixArchived' => true, 'archiveUrls' => ['security' => '', 'updates' => '']]);
        $packager->refresh();
    }

    /**
     * @return void
     */
    public function testSecurityRepositoryOnTheMainHost()
    {
        $fs = self::createFilesystem(
            "deb http://deb.debian.org/debian-security buster/updates main\n",
            "deb http://archive.debian.org/debian-security buster/updates main\n"
        );
        $fs->shouldReceive('exists')->with('/etc/apt/sources.list.d/buster.list')->andReturn(false);
        (new OnDebian(self::createOS(OS\Debian::VERSION_BUSTER, 'buster', $fs), ['fixArchived' => true]))->refresh();
    }

    /**
     * @return void
     */
    public function testNotArchived()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldNotReceive('readFile');
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('apt-get', ['update', '-qy'])->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $packager = new OnDebian(self::createOS(OS\Debian::VERSION_BOOKWORM, 'bookworm', $fs), ['fixArchived' => true]);
        $packager->refresh();
    }
}
