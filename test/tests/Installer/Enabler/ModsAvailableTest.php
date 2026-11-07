<?php

namespace IPE\Test\Installer\Enabler;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Installer\Enabler\ModsAvailable;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ModsAvailableTest extends TestCase
{
    /**
     * @param string $iniScanDir
     *
     * @return \IPE\PHPInfo
     */
    private static function createPHPInfo($iniScanDir)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getIniScanDir')->andReturn($iniScanDir);

        return $php;
    }

    /**
     * @param list<string> $directories the existing directories
     * @param array<string, string> $commands the paths of the commands that can be resolved
     *
     * @return \IPE\Filesystem&\Mockery\MockInterface
     */
    private static function createFilesystem(array $directories = [], array $commands = [])
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('isDirectory')->andReturnUsing(static function ($path) use ($directories) {
            return in_array($path, $directories, true);
        });
        foreach ($commands as $name => $path) {
            $fs->shouldReceive('resolveCommand')->with($name)->andReturn($path);
        }
        $fs->shouldReceive('resolveCommand')->andReturn('');

        return $fs;
    }

    /**
     * @param \IPE\Filesystem $fs
     *
     * @return \IPE\Installer\Enabler\ModsAvailable
     */
    private static function detectPHP8($fs)
    {
        $modsAvailable = ModsAvailable::detect(self::createPHPInfo('/etc/php/8.3/cli/conf.d'), $fs);
        self::assertNotNull($modsAvailable);

        return $modsAvailable;
    }

    /**
     * @return \IPE\Filesystem&\Mockery\MockInterface
     */
    private static function createPHP8Filesystem()
    {
        return self::createFilesystem(['/etc/php/8.3/mods-available'], ['phpenmod' => '/usr/sbin/phpenmod']);
    }

    /**
     * @return void
     */
    public function testDetectPHP8()
    {
        $modsAvailable = self::detectPHP8(self::createPHP8Filesystem());
        self::assertSame('/etc/php/8.3/mods-available', $modsAvailable->getDirectory());
        self::assertSame('/etc/php/8.3/mods-available/apcu.ini', $modsAvailable->getIniFile('apcu'));
    }

    /**
     * @return void
     */
    public function testDetectPHP5()
    {
        $fs = self::createFilesystem(['/etc/php5/mods-available'], ['php5enmod' => '/usr/sbin/php5enmod']);
        $modsAvailable = ModsAvailable::detect(self::createPHPInfo('/etc/php5/fpm/conf.d'), $fs);
        self::assertNotNull($modsAvailable);
        self::assertSame('/etc/php5/mods-available/apcu.ini', $modsAvailable->getIniFile('apcu'));
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/sbin/php5enmod', ['apcu'])))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/sbin/php5query', ['-s', 'fpm', '-q', '-m', 'apcu'])->withOutput(Command::OUTPUT_DISCARD)))->andReturn([0, '']);
        $modsAvailable->enable('apcu');
    }

    /**
     * @return array<array{string, list<string>, array<string, string>}>
     */
    public static function provideNotDetectedCases()
    {
        $directories = ['/etc/php/8.3/mods-available', '/etc/php5/mods-available', '/usr/local/etc/mods-available'];
        $commands = ['phpenmod' => '/usr/sbin/phpenmod', 'php5enmod' => '/usr/sbin/php5enmod'];

        return [
            ['', $directories, $commands],
            ['/usr/local/etc/php/conf.d', $directories, $commands],
            ['/etc/php/cli/conf.d', $directories, $commands],
            ['/etc/php5/5.6/cli/conf.d', $directories, $commands],
            ['/etc/php/8.3/cli/conf.d', [], $commands],
            ['/etc/php/8.3/cli/conf.d', $directories, ['php5enmod' => '/usr/sbin/php5enmod']],
            ['/etc/php5/cli/conf.d', $directories, ['phpenmod' => '/usr/sbin/phpenmod']],
        ];
    }

    /**
     * @dataProvider provideNotDetectedCases
     *
     * @param string $iniScanDir
     * @param list<string> $directories
     * @param array<string, string> $commands
     *
     * @return void
     */
    public function testNotDetected($iniScanDir, array $directories, array $commands)
    {
        self::assertNull(ModsAvailable::detect(self::createPHPInfo($iniScanDir), self::createFilesystem($directories, $commands)));
    }

    /**
     * @return array<array{int, bool}>
     */
    public static function provideEnableCases()
    {
        return [
            [0, true],
            // Not enabled
            [1, false],
            // Disabled by the local administrator
            [32, false],
        ];
    }

    /**
     * @dataProvider provideEnableCases
     *
     * @param int $queryRC the exit code of phpquery after phpenmod
     * @param bool $enabled
     *
     * @return void
     */
    public function testEnable($queryRC, $enabled)
    {
        $fs = self::createPHP8Filesystem();
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/sbin/phpenmod', ['-v', '8.3', '-r', 'apcu'])))->andReturn([0, 'WARNING: something']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/sbin/phpquery', ['-v', '8.3', '-s', 'cli', '-q', '-m', 'apcu'])->withOutput(Command::OUTPUT_DISCARD)))->andReturn([$queryRC, '']);
        $modsAvailable = self::detectPHP8($fs);
        if (!$enabled) {
            $this->expectException(Exception\IO::class);
            $this->expectExceptionMessage("Failed to enable the module apcu with /usr/sbin/phpenmod:\nWARNING: something");
        }
        $modsAvailable->enable('apcu');
    }

    /**
     * @dataProvider provideEnableCases
     *
     * @param int $queryRC the exit code of phpquery after phpdismod
     * @param bool $enabled
     *
     * @return void
     */
    public function testDisable($queryRC, $enabled)
    {
        $fs = self::createPHP8Filesystem();
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/sbin/phpdismod', ['-v', '8.3', '-r', 'apcu'])))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/sbin/phpquery', ['-v', '8.3', '-s', 'cli', '-q', '-m', 'apcu'])->withOutput(Command::OUTPUT_DISCARD)))->andReturn([$queryRC, '']);
        $modsAvailable = self::detectPHP8($fs);
        if ($enabled) {
            $this->expectException(Exception\IO::class);
            $this->expectExceptionMessage('Failed to disable the module apcu with /usr/sbin/phpdismod');
        }
        $modsAvailable->disable('apcu');
    }

    /**
     * @return void
     */
    public function testWriteNewIniFile()
    {
        $fs = self::createPHP8Filesystem();
        $fs->shouldReceive('exists')->with('/etc/php/8.3/mods-available/apcu.ini')->andReturn(false);
        $fs->shouldReceive('writeFile')->once()->with(
            '/etc/php/8.3/mods-available/apcu.ini',
            "; Created by install-php-extensions, which may overwrite this file: remove this line to prevent it\n; priority=80\nextension=apcu.so\n"
        );
        self::assertSame('/etc/php/8.3/mods-available/apcu.ini', self::detectPHP8($fs)->writeIniFile('apcu', 80, "extension=apcu.so\n"));
    }

    /**
     * @return void
     */
    public function testOverwriteOurIniFile()
    {
        $fs = self::createPHP8Filesystem();
        $fs->shouldReceive('exists')->with('/etc/php/8.3/mods-available/apcu.ini')->andReturn(true);
        $fs->shouldReceive('readFile')->with('/etc/php/8.3/mods-available/apcu.ini')->andReturn("; Created by install-php-extensions, with an older message\n; priority=20\nextension=apcu.so\n");
        $fs->shouldReceive('writeFile')->once()->with('/etc/php/8.3/mods-available/apcu.ini', M::type('string'));
        self::detectPHP8($fs)->writeIniFile('apcu', 80, "extension=apcu.so\n");
    }

    /**
     * @return array<array{string}>
     */
    public static function provideForeignIniFiles()
    {
        return [
            ["; configuration for php apcu module\n; priority=20\nextension=apcu.so\n"],
            // The administrator removed our line
            ["; priority=80\nextension=apcu.so\n"],
            ["; priority=80\n; Created by install-php-extensions\nextension=apcu.so\n"],
        ];
    }

    /**
     * @dataProvider provideForeignIniFiles
     *
     * @param string $content
     *
     * @return void
     */
    public function testDontOverwriteForeignIniFiles($content)
    {
        $fs = self::createPHP8Filesystem();
        $fs->shouldReceive('exists')->with('/etc/php/8.3/mods-available/apcu.ini')->andReturn(true);
        $fs->shouldReceive('readFile')->with('/etc/php/8.3/mods-available/apcu.ini')->andReturn($content);
        $fs->shouldNotReceive('writeFile');
        $this->expectException(Exception\IO::class);
        $this->expectExceptionMessage("The file /etc/php/8.3/mods-available/apcu.ini already exists, and it doesn't start with the line '; Created by install-php-extensions': remove or rename it to install the module apcu");
        self::detectPHP8($fs)->writeIniFile('apcu', 80, "extension=apcu.so\n");
    }
}
