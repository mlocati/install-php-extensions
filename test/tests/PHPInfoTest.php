<?php

namespace IPE\Test;

use IPE\Exception;
use IPE\Filesystem;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\PHPInfoTest\FakeExtension;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class PHPInfoTest extends TestCase
{
    /**
     * @return void
     */
    public function testPHPInfo()
    {
        $fs = self::createLocalFilesystem();

        $current = PHPInfo::fromCurrentPHP($fs);
        $fromPHP = PHPInfo::fromPHPBin(PHP_BINARY, $fs);
        self::assertNotSame($current, $fromPHP);
        self::assertEquals($current, $fromPHP);
        self::assertMatchesRegularExpression('/^[1-9]\d*\.\d+\.\d+/', $current->getVersion());
        self::assertSame(PHP_VERSION, PHP_VERSION);
        self::assertSame(PHP_MAJOR_VERSION, $current->getMajorVersion());
        self::assertSame(PHP_MAJOR_VERSION * 100 + PHP_MINOR_VERSION, $current->getMajorMinorVersion());
        self::assertSame(PHP_INT_SIZE * 8, $current->getBits());
        self::assertSame(ZEND_THREAD_SAFE, $current->isThreadSafe());
        self::assertContains('Core', $current->getInstalledExtensions());
        self::assertContains('SPL', $current->getInstalledExtensions());
        $os = M::mock(OS::class);
        self::assertTrue($current->isExtensionInstalled(new FakeExtension($current, $os, ['Core'])));
        self::assertTrue($current->isExtensionInstalled(new FakeExtension($current, $os, ['core'])));
        $extension = new FakeExtension($current, $os, ['IPE Test']);
        self::assertFalse($current->isExtensionInstalled($extension));
        $instance = $current->markExtensionAsInstalled($extension);
        self::assertSame($current, $instance);
        self::assertContains('IPE Test', $current->getInstalledExtensions());
        self::assertTrue($current->isExtensionInstalled($extension));
        self::assertGreaterThan(20000000, $current->getAPIVersion());
        $current->addInstalledExtensions(['Another One']);
        self::assertContains('Another One', $current->getInstalledExtensions());
    }

    /**
     * @return void
     */
    public function testFromPHPBinWithGeneralInfo()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('getPathSeparator')->andReturn(':');
        $fs->shouldReceive('exists')->andReturn(true);
        $fs->shouldReceive('isFile')->andReturn(true);
        $fs->shouldReceive('getRealPath')->andReturnUsing(static function ($path) {
            return $path;
        });
        $fs->shouldReceive('run')->with(M::type(Filesystem\Command::class))->andReturn(
            [0, implode("\n", ['8.4.14', '8', '4', '8', '0', '/usr/local/lib/php/extensions/no-debug-non-zts-20240924', '/usr/local/etc/php/conf.d', 'Core', 'json', ''])],
            [0, implode("\n", [
                'phpinfo()',
                'PHP Version => 8.4.14',
                '',
                'System => Linux',
                "Configure Command =>  './configure'  '--with-libdir=lib64'",
                'Loaded Configuration File => (none)',
                'Scan this dir for additional .ini files => /usr/local/etc/php/conf.d',
                'PHP API => 20240924',
                'PHP Extension => 20240924',
                '',
            ])]
        );
        $php = PHPInfo::fromPHPBin('/usr/local/bin/php', $fs);
        self::assertSame('8.4.14', $php->getVersion());
        self::assertSame(804, $php->getMajorMinorVersion());
        self::assertSame(64, $php->getBits());
        self::assertFalse($php->isThreadSafe());
        self::assertSame(20240924, $php->getAPIVersion());
        self::assertSame("'./configure'  '--with-libdir=lib64'", $php->getConfigureCommand());
        self::assertSame('', $php->getLoadedIniFile());
        self::assertSame('/usr/local/etc/php/conf.d', $php->getIniScanDir());
        self::assertSame(['/usr/local/etc/php/conf.d'], $php->getIniScanDirs());
        self::assertSame(['Core', 'json'], $php->getInstalledExtensions());
    }

    /**
     * @return list<array{string, string, string, list<string>}>
     */
    public static function provideIniScanDirCases()
    {
        $defaultDir = '/usr/local/etc/php/conf.d';

        return [
            [':/app', $defaultDir, $defaultDir, [$defaultDir, '/app']],
            ["{$defaultDir}:/app", $defaultDir, $defaultDir, [$defaultDir, '/app']],
            ["/app:{$defaultDir}", $defaultDir, $defaultDir, ['/app', $defaultDir]],
            [":/app:{$defaultDir}", $defaultDir, $defaultDir, [$defaultDir, '/app']],
            ['/missing:/app', $defaultDir, '/app', ['/missing', '/app']],
            ['/missing', $defaultDir, '/missing', ['/missing']],
            ['/missing:/missing2', $defaultDir, '/missing', ['/missing', '/missing2']],
            ['', $defaultDir, '', []],
            [':/app', '', '/app', ['/app']],
            [':', '', '', []],
        ];
    }

    /**
     * @dataProvider provideIniScanDirCases
     *
     * @param string $scanDirs the directories listed by phpinfo() (from the PHP_INI_SCAN_DIR environment variable, if set)
     * @param string $defaultScanDir the value of PHP_CONFIG_FILE_SCAN_DIR
     * @param string $expectedDir
     * @param list<string> $expectedDirs
     *
     * @return void
     */
    public function testIniScanDir($scanDirs, $defaultScanDir, $expectedDir, array $expectedDirs)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('getPathSeparator')->andReturn(':');
        $fs->shouldReceive('isFile')->andReturn(true);
        $fs->shouldReceive('exists')->andReturnUsing(static function ($path) {
            return !in_array($path, ['/missing', '/missing2'], true);
        });
        $fs->shouldReceive('getRealPath')->andReturnUsing(static function ($path) {
            return $path;
        });
        $fs->shouldReceive('run')->with(M::type(Filesystem\Command::class))->andReturn(
            [0, implode("\n", ['8.4.14', '8', '4', '8', '0', '/usr/local/lib/php/extensions/no-debug-non-zts-20240924', $defaultScanDir, ''])],
            [0, implode("\n", [
                'Loaded Configuration File => (none)',
                'Scan this dir for additional .ini files => ' . ($scanDirs === '' ? '(none)' : $scanDirs),
                'PHP API => 20240924',
                '',
            ])]
        );
        $php = PHPInfo::fromPHPBin('/usr/local/bin/php', $fs);
        self::assertSame($expectedDir, $php->getIniScanDir());
        self::assertSame($expectedDirs, $php->getIniScanDirs());
    }

    /**
     * @return void
     */
    public function testFromPHPBinWithCommandName()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('exists')->andReturn(true);
        $fs->shouldReceive('isFile')->never();
        $fs->shouldReceive('resolveCommand')->once()->with('php8.4')->andReturn('/usr/bin/php8.4');
        $fs->shouldReceive('getRealPath')->andReturnUsing(static function ($path) {
            return $path;
        });
        $fs->shouldReceive('run')->with(M::type(Filesystem\Command::class))->andReturn(
            [0, implode("\n", ['8.4.14', '8', '4', '8', '0', '/usr/lib/php/20240924', '', 'Core', ''])],
            [0, "PHP API => 20240924\n"]
        );
        $php = PHPInfo::fromPHPBin('php8.4', $fs);
        self::assertSame('/usr/bin/php8.4', $php->getPHPBin());
    }

    /**
     * @return list<array{non-empty-string, string}>
     */
    public static function provideFromPHPBinNotFoundCases()
    {
        return [
            ['php99', "Unable to find the PHP command 'php99'"],
            ['/nope/php', "The PHP binary '/nope/php' does not exist"],
            ['bin\\php', "The PHP binary 'bin\\php' does not exist"],
        ];
    }

    /**
     * @dataProvider provideFromPHPBinNotFoundCases
     *
     * @param non-empty-string $phpBin
     * @param string $expectedMessage
     *
     * @return void
     */
    public function testFromPHPBinNotFound($phpBin, $expectedMessage)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->andReturn('');
        $fs->shouldReceive('isFile')->andReturn(false);
        $fs->shouldReceive('run')->never();
        $this->expectException(Exception\InvalidArgument::class);
        $this->expectExceptionMessage($expectedMessage);
        PHPInfo::fromPHPBin($phpBin, $fs);
    }

    /**
     * @return list<array{string, string}>
     */
    public static function provideVersionCases()
    {
        return [
            ['8.4.14', '8.4.14'],
            ['8.1.2-1ubuntu2.14', '8.1.2'],
            ['7.4.33+deb11u1', '7.4.33'],
            ['5.6.40-0+deb8u12', '5.6.40'],
            ['8.6.0-dev', '8.6.0'],
            ['8.5.0RC1', '8.5.0'],
            ['foo', ''],
            ['8.4', ''],
        ];
    }

    /**
     * @dataProvider provideVersionCases
     *
     * @param string $version
     * @param string $expectedBaseVersion empty string if the version must be rejected
     *
     * @return void
     */
    public function testBaseVersion($version, $expectedBaseVersion)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('exists')->andReturn(true);
        $fs->shouldReceive('isFile')->andReturn(true);
        $fs->shouldReceive('getRealPath')->andReturnUsing(static function ($path) {
            return $path;
        });
        $fs->shouldReceive('run')->with(M::type(Filesystem\Command::class))->andReturn(
            [0, implode("\n", [$version, '8', '4', '8', '0', '/usr/local/lib/php/extensions/no-debug-non-zts-20240924', '', ''])],
            [0, "PHP API => 20240924\n"]
        );
        try {
            $php = PHPInfo::fromPHPBin('/usr/bin/php', $fs);
        } catch (Exception\IO $x) {
            self::assertSame('', $expectedBaseVersion, $x->getMessage());
            self::assertStringContainsString($version, $x->getMessage());

            return;
        }
        self::assertNotSame('', $expectedBaseVersion, "{$version} should have been rejected");
        self::assertSame($version, $php->getVersion());
        self::assertSame($expectedBaseVersion, $php->getBaseVersion());
    }
}
