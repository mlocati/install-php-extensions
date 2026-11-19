<?php

namespace IPE\Test;

use IPE\Architecture;
use IPE\Exception;
use IPE\Filesystem;
use IPE\OS;
use IPE\PHPInfo;
use IPE\PHPInfo\SSLLibrary;
use IPE\Test\Support\PHPInfoTest\FakeExtension;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class PHPInfoTest extends TestCase
{
    /**
     * @return \IPE\OS
     */
    private static function createOS(Filesystem $fs)
    {
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        // A machine architecture that PHP must not use when it reports its own
        $os->shouldReceive('getArchitecture')->andReturn(new Architecture('unknown'));

        return $os;
    }

    /**
     * @return void
     */
    public function testPHPInfo()
    {
        $os = self::createOS(self::createLocalFilesystem());

        $current = PHPInfo::fromCurrentPHP($os);
        $fromPHP = PHPInfo::fromPHPBin(PHP_BINARY, $os);
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
        $sslLibrary = $current->getSSLLibrary();
        self::assertSame($current, $current->detectSSLLibrary());
        self::assertEquals($sslLibrary, $current->getSSLLibrary());
        if (DIRECTORY_SEPARATOR === '\\') {
            self::assertContains($current->getArchitecture()->getName(), [Architecture::X86, Architecture::X64, Architecture::ARM64]);
            self::assertMatchesRegularExpression('/^V[CS]\d+$/', $current->getCompilerID());
        } else {
            self::assertSame('', $current->getCompilerID());
        }
    }

    /**
     * @return list<array{string, string, string, string}>
     */
    public static function provideWindowsBuildCases()
    {
        return [
            ['x86', Architecture::X86, 'API20121212,NTS,VC11', 'VC11'],
            ['x64', Architecture::X64, 'API20170718,TS,VC15', 'VC15'],
            ['x64', Architecture::X64, 'API20240924,NTS,VS17', 'VS17'],
            ['arm64', Architecture::ARM64, 'API20240924,TS,debug,VS17', 'VS17'],
            ['x64', Architecture::X64, 'API20240924,NTS,VS19,extra', 'VS19'],
            ['x64', Architecture::X64, 'API20240924,NTS,debug', ''],
            ['x64', Architecture::X64, 'API20240924,NTS,clang', ''],
            ['x64', Architecture::X64, '', ''],
        ];
    }

    /**
     * @dataProvider provideWindowsBuildCases
     *
     * @param string $buildArchitecture
     * @param string $expectedArchitecture
     * @param string $extensionBuild
     * @param string $expectedCompilerID
     *
     * @return void
     */
    public function testWindowsBuild($buildArchitecture, $expectedArchitecture, $extensionBuild, $expectedCompilerID)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('\\');
        $fs->shouldReceive('isFile')->andReturn(true);
        $fs->shouldReceive('getRealPath')->andReturnUsing(static function ($path) {
            return $path;
        });
        $fs->shouldReceive('run')->with(M::type(Filesystem\Command::class))->andReturn(
            [0, implode("\r\n", ['8.4.14', '8', '0', 'C:\\php\\ext', '', 'Core', ''])],
            [0, implode("\r\n", [
                'phpinfo()',
                'PHP Version => 8.4.14',
                '',
                'System => Windows NT MACHINE 10.0 build 26100 (Windows 11) AMD64',
                'Build Date => Oct 21 2025 18:51:05',
                'Build System => Microsoft Windows Server 2022 Datacenter [10.0.20348]',
                'Compiler => Visual C++ 2022',
                "Architecture => {$buildArchitecture}",
                'Configure Command => cscript /nologo /e:jscript configure.js  "--enable-snapshot-build"',
                'Loaded Configuration File => (none)',
                'Scan this dir for additional .ini files => (none)',
                'PHP API => 20240924',
                'PHP Extension => 20240924',
                'Zend Extension Build => ' . ($extensionBuild === '' ? '' : 'API4' . substr($extensionBuild, 3)),
                'PHP Extension Build => ' . $extensionBuild,
                '',
            ])],
            [0, '']
        );
        $php = PHPInfo::fromPHPBin('C:\\php\\php.exe', self::createOS($fs));
        // The architecture reported by PHP wins over the one of the machine
        self::assertSame($expectedArchitecture, $php->getArchitecture()->getName());
        self::assertSame($expectedCompilerID, $php->getCompilerID());
        self::assertSame('', $php->getLoadedIniFile());
        self::assertSame('', $php->getIniScanDir());
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
            [0, implode("\n", ['8.4.14', '8', '0', '/usr/local/lib/php/extensions/no-debug-non-zts-20240924', '/usr/local/etc/php/conf.d', 'Core', 'json', ''])],
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
                'PHP Extension Build => API20240924,NTS',
                '',
            ])],
            [0, 'OpenSSL 3.0.15 3 Sep 2024']
        );
        $php = PHPInfo::fromPHPBin('/usr/local/bin/php', self::createOS($fs));
        self::assertSame('', $php->getCompilerID());
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
        $sslLibrary = $php->getSSLLibrary();
        self::assertNotNull($sslLibrary);
        self::assertSame(SSLLibrary::TYPE_OPENSSL, $sslLibrary->getType());
        self::assertSame('3.0.15', $sslLibrary->getVersion());
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
            [0, implode("\n", ['8.4.14', '8', '0', '/usr/local/lib/php/extensions/no-debug-non-zts-20240924', $defaultScanDir, ''])],
            [0, implode("\n", [
                'Loaded Configuration File => (none)',
                'Scan this dir for additional .ini files => ' . ($scanDirs === '' ? '(none)' : $scanDirs),
                'PHP API => 20240924',
                '',
            ])],
            [0, '']
        );
        $php = PHPInfo::fromPHPBin('/usr/local/bin/php', self::createOS($fs));
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
            [0, implode("\n", ['8.4.14', '8', '0', '/usr/lib/php/20240924', '', 'Core', ''])],
            [0, "PHP API => 20240924\n"],
            [0, '']
        );
        $php = PHPInfo::fromPHPBin('php8.4', self::createOS($fs));
        self::assertSame('/usr/bin/php8.4', $php->getPHPBin());
        self::assertNull($php->getSSLLibrary());
        self::assertSame(['Core'], $php->getInstalledExtensions());
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
        PHPInfo::fromPHPBin($phpBin, self::createOS($fs));
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
            [0, implode("\n", [$version, '8', '0', '/usr/local/lib/php/extensions/no-debug-non-zts-20240924', '', ''])],
            [0, "PHP API => 20240924\n"],
            [0, '']
        );
        try {
            $php = PHPInfo::fromPHPBin('/usr/bin/php', self::createOS($fs));
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
