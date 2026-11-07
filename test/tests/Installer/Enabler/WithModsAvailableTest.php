<?php

namespace IPE\Test\Installer\Enabler;

use IPE\Exception;
use IPE\Extension\Resolved;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Installer\CompiledExtension;
use IPE\Installer\Enabler;
use IPE\Installer\Enabler\ModsAvailable;
use IPE\Installer\Enabler\Options;
use IPE\Installer\Enabler\WithModsAvailable;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class WithModsAvailableTest extends TestCase
{
    const MODS_AVAILABLE_DIR = '/etc/php/8.3/mods-available';

    const INI_DIR = '/etc/php/8.3/cli/conf.d';

    const MODULES_BEFORE = "[PHP Modules]\nCore\njson\n\n[Zend Modules]\n";

    /**
     * @return \IPE\PHPInfo&\Mockery\MockInterface
     */
    private static function createPHPInfo()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/bin/php');
        $php->shouldReceive('getMajorMinorVersion')->andReturn(803);
        $php->shouldReceive('getIniScanDir')->andReturn(self::INI_DIR);

        return $php;
    }

    /**
     * @return \IPE\Filesystem&\Mockery\MockInterface
     */
    private static function createFilesystem()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isDirectory')->with(self::MODS_AVAILABLE_DIR)->andReturn(true);
        $fs->shouldReceive('resolveCommand')->with('phpenmod')->andReturn('/usr/sbin/phpenmod');

        return $fs;
    }

    /**
     * @param \IPE\Filesystem $fs
     * @param \IPE\PHPInfo|null $php
     * @param \IPE\Installer\Enabler\Options|null $options
     *
     * @return \IPE\Installer\Enabler\WithModsAvailable
     */
    private static function createEnabler($fs, $php = null, $options = null)
    {
        if ($php === null) {
            $php = self::createPHPInfo();
        }
        $modsAvailable = ModsAvailable::detect($php, $fs);
        self::assertNotNull($modsAvailable);

        return new WithModsAvailable($php, $fs, new \IPE\Logger(), $modsAvailable, $options === null ? new Options() : $options);
    }

    /**
     * @param non-empty-string $moduleName
     *
     * @return \IPE\Installer\CompiledExtension
     */
    private static function createCompiledExtension($moduleName)
    {
        $resolved = new Resolved(null, '/tmp/ipe/src', '1.0.0', Resolved::ORIGIN_DIRECTORY);

        return new CompiledExtension($resolved, $moduleName, "/usr/lib/php/20230831/{$moduleName}.so", false);
    }

    /**
     * @param non-empty-string $moduleName
     *
     * @return \IPE\Filesystem\Command
     */
    private static function createPHPQueryCommand($moduleName)
    {
        return Command::create('/usr/sbin/phpquery', ['-v', '8.3', '-s', 'cli', '-q', '-m', $moduleName])->withOutput(Command::OUTPUT_DISCARD);
    }

    /**
     * @param \IPE\Filesystem&\Mockery\MockInterface $fs
     * @param string $modulesAfter the output of php -m once the module is enabled
     *
     * @return void
     */
    private static function expectModuleListings($fs, $modulesAfter)
    {
        $fs->shouldReceive('run')
            ->with(self::isCommand(Command::create('/usr/bin/php', ['-d', 'display_startup_errors=1', '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-m'])))
            ->andReturn([0, self::MODULES_BEFORE], [0, $modulesAfter])
        ;
    }

    /**
     * @return void
     */
    public function testChosenByTheContainer()
    {
        $app = \IPE\createApp();
        $app->instance(PHPInfo::class, self::createPHPInfo());
        $app->instance(Filesystem::class, self::createFilesystem());
        self::assertInstanceOf(WithModsAvailable::class, $app->make(Enabler::class));
    }

    /**
     * @return void
     */
    public function testEnable()
    {
        $iniFile = self::MODS_AVAILABLE_DIR . '/apcu.ini';
        $fs = self::createFilesystem();
        $fs->shouldReceive('exists')->with($iniFile)->andReturn(false);
        self::expectModuleListings($fs, "[PHP Modules]\napcu\nCore\njson\n\n[Zend Modules]\n");
        $fs->shouldReceive('run')
            ->with(self::isCommand(Command::create('/usr/bin/php', ['-d', 'display_startup_errors=1', '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-r', 'return;'])->withEnvironment(['LD_BIND_NOW' => '1'])))
            ->andReturn([0, ''], [0, ''])
        ;
        $fs->shouldReceive('writeFile')->once()->ordered()->with(self::INI_DIR . '/80-apcu--temp.ini', "extension=apcu.so\n");
        $fs->shouldReceive('deleteFile')->once()->ordered()->with(self::INI_DIR . '/80-apcu--temp.ini');
        $fs->shouldReceive('writeFile')->once()->ordered()->with($iniFile, ModsAvailable::MARKER . ", which may overwrite this file: remove this line to prevent it\n; priority=80\nextension=apcu.so\n");
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/sbin/phpenmod', ['-v', '8.3', '-r', 'apcu'])))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/sbin/phpdismod', ['-v', '8.3', '-r', 'apcu'])))->andReturn([0, '']);
        // After phpenmod, when building the report, and after phpdismod
        $fs->shouldReceive('run')->times(3)->with(self::isCommand(self::createPHPQueryCommand('apcu')))->andReturn([0, ''], [0, ''], [32, '']);
        $php = self::createPHPInfo();
        $php->shouldReceive('addInstalledExtensions')->once()->with(['apcu']);
        $compiled = self::createCompiledExtension('apcu');

        $enabler = self::createEnabler($fs, $php);
        self::assertSame(['apcu'], $enabler->enable($compiled));
        self::assertSame($iniFile, $enabler->getIniFile($compiled));
        self::assertTrue($enabler->isIniFileActive($iniFile));
        self::assertSame([], $enabler->disableEnabledExtensions());
    }

    /**
     * @return void
     */
    public function testEnableWithoutCheck()
    {
        $iniFile = self::MODS_AVAILABLE_DIR . '/apcu.ini';
        $fs = self::createFilesystem();
        $fs->shouldReceive('exists')->with($iniFile)->andReturn(false);
        self::expectModuleListings($fs, "[PHP Modules]\napcu\nCore\njson\n\n[Zend Modules]\n");
        $fs->shouldReceive('writeFile')->once()->with($iniFile, M::type('string'));
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('/usr/sbin/phpenmod', ['-v', '8.3', '-r', 'apcu'])))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->with(self::isCommand(self::createPHPQueryCommand('apcu')))->andReturn([0, '']);
        $php = self::createPHPInfo();
        $php->shouldReceive('addInstalledExtensions')->once()->with(['apcu']);

        self::assertSame(['apcu'], self::createEnabler($fs, $php, (new Options())->withSkipCheck(true))->enable(self::createCompiledExtension('apcu')));
    }

    /**
     * @return void
     */
    public function testDoesNotOverwriteForeignIniFiles()
    {
        $iniFile = self::MODS_AVAILABLE_DIR . '/redis.ini';
        $fs = self::createFilesystem();
        $fs->shouldReceive('exists')->with($iniFile)->andReturn(true);
        $fs->shouldReceive('readFile')->with($iniFile)->andReturn("; configuration for php redis module\n; priority=20\nextension=redis.so\n");
        $fs->shouldNotReceive('writeFile');
        $fs->shouldNotReceive('run');
        $this->expectException(Exception\IO::class);
        $this->expectExceptionMessage("The file {$iniFile} already exists");
        self::createEnabler($fs)->enable(self::createCompiledExtension('redis'));
    }

    /**
     * @return array<array{\IPE\Installer\Enabler\Options, string}>
     */
    public static function provideInvalidOptions()
    {
        return [
            [(new Options())->withIniBasename('{priority}_{name}'), "Invalid name of the ini files: '{priority}_{name}' (PHP uses the directory " . self::MODS_AVAILABLE_DIR . ', where the names of the ini files are determined by phpenmod)'],
            [(new Options())->withIniBasename(Enabler::INI_BASENAME_COMPAT), "Invalid name of the ini files: 'compat'"],
            [(new Options())->withCreateEnableScripts(true), "The scripts that enable the extensions can't be created"],
        ];
    }

    /**
     * @dataProvider provideInvalidOptions
     *
     * @param string $expectedMessage
     *
     * @return void
     */
    public function testInvalidOptions(Options $options, $expectedMessage)
    {
        $this->expectException(Exception\InvalidArgument::class);
        $this->expectExceptionMessage($expectedMessage);
        self::createEnabler(self::createFilesystem(), null, $options);
    }

    /**
     * @return array<array{int, bool, bool}>
     */
    public static function provideToggleCases()
    {
        return [
            [0, true, false],
            [0, false, true],
            [1, true, true],
            [32, false, false],
        ];
    }

    /**
     * @dataProvider provideToggleCases
     *
     * @param int $queryRC the exit code of phpquery before the change
     * @param bool $enable
     * @param bool $changes
     *
     * @return void
     */
    public function testToggleIniFile($queryRC, $enable, $changes)
    {
        $iniFile = self::MODS_AVAILABLE_DIR . '/apcu.ini';
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with($iniFile)->andReturn(true);
        $fs->shouldReceive('readFile')->with($iniFile)->andReturn(ModsAvailable::MARKER . "\n; priority=80\nextension=apcu.so\n");
        $fs->shouldNotReceive('writeFile');
        if ($changes) {
            $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create($enable ? '/usr/sbin/phpenmod' : '/usr/sbin/phpdismod', ['-v', '8.3', '-r', 'apcu'])))->andReturn([0, '']);
            $fs->shouldReceive('run')->twice()->with(self::isCommand(self::createPHPQueryCommand('apcu')))->andReturn([$queryRC, ''], [$queryRC === 0 ? 32 : 0, '']);
        } else {
            $fs->shouldReceive('run')->once()->with(self::isCommand(self::createPHPQueryCommand('apcu')))->andReturn([$queryRC, '']);
        }
        $enabler = self::createEnabler($fs);

        self::assertSame($changes, $enable ? $enabler->enableIniFile($iniFile) : $enabler->disableIniFile($iniFile));
    }

    /**
     * @return void
     */
    public function testIniFilesOutsideModsAvailable()
    {
        $iniFile = self::INI_DIR . '/99-vendor.ini';
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with($iniFile)->andReturn(true);
        $fs->shouldReceive('readFile')->with($iniFile)->andReturn("extension=vendor.so\n");
        $fs->shouldReceive('writeFile')->once()->with($iniFile, ";extension=vendor.so\n");
        $fs->shouldNotReceive('run');
        $enabler = self::createEnabler($fs);

        self::assertTrue($enabler->isIniFileActive($iniFile));
        self::assertTrue($enabler->disableIniFile($iniFile));
    }
}
