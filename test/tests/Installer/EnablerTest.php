<?php

namespace IPE\Test\Installer;

use IPE\Exception;
use IPE\Extension\Manifest;
use IPE\Extension\Resolved;
use IPE\Extensions\http;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Installer\CompiledExtension;
use IPE\Installer\Enabler;
use IPE\Installer\Enabler\Options;
use IPE\OS;
use IPE\PHPInfo;
use IPE\PHPInfo\SSLLibrary;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class EnablerTest extends TestCase
{
    const INI_DIR = '/usr/local/etc/php/conf.d';

    const MODULES_BEFORE = "[PHP Modules]\nCore\njson\n\n[Zend Modules]\n";

    /**
     * @param int $majorMinorVersion
     *
     * @return \IPE\PHPInfo&\Mockery\MockInterface
     */
    private static function createPHPInfo($majorMinorVersion = 804)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/local/bin/php');
        $php->shouldReceive('getMajorMinorVersion')->andReturn($majorMinorVersion);
        $php->shouldReceive('getIniScanDir')->andReturn(self::INI_DIR);
        $php->shouldReceive('getSSLLibrary')->andReturn(null)->byDefault();

        return $php;
    }

    /**
     * @return \IPE\Filesystem&\Mockery\MockInterface
     */
    private static function createFilesystem()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');

        return $fs;
    }

    /**
     * @param non-empty-string $moduleName
     * @param bool $zendExtension
     * @param \IPE\Extension\Manifest|null $manifest
     *
     * @return \IPE\Installer\CompiledExtension
     */
    private static function createCompiledExtension($moduleName, $zendExtension = false, $manifest = null)
    {
        $resolved = new Resolved(null, '/tmp/ipe/src', '1.0.0', Resolved::ORIGIN_DIRECTORY, $manifest);

        return new CompiledExtension($resolved, $moduleName, "/usr/local/lib/php/extensions/no-debug-non-zts-20240924/{$moduleName}.so", $zendExtension);
    }

    /**
     * @param \IPE\Filesystem&\Mockery\MockInterface $fs
     * @param string $modulesAfter the output of php -m once the temporary ini file is in place
     * @param string $outputBefore the output of PHP without doing anything
     * @param string $outputAfter the output of PHP without doing anything, once the temporary ini file is in place
     * @param int $rcAfter the exit code of PHP without doing anything, once the temporary ini file is in place
     *
     * @return void
     */
    private static function expectModuleListings($fs, $modulesAfter, $outputBefore = '', $outputAfter = '', $rcAfter = 0)
    {
        $fs->shouldReceive('run')
            ->with(self::isCommand(Command::create('/usr/local/bin/php', ['-d', 'display_startup_errors=1', '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-m'])))
            ->andReturn([0, self::MODULES_BEFORE], [0, $modulesAfter])
        ;
        $fs->shouldReceive('run')
            ->with(self::isCommand(Command::create('/usr/local/bin/php', ['-d', 'display_startup_errors=1', '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-r', 'return;'])->withEnvironment(['LD_BIND_NOW' => '1'])))
            ->andReturn([0, $outputBefore], [$rcAfter, $outputAfter])
        ;
    }

    /**
     * @return void
     */
    public function testEnableWithDefaultIniBasename()
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, "[PHP Modules]\napcu\nCore\njson\n\n[Zend Modules]\n");
        $fs->shouldReceive('writeFile')->once()->ordered()->with(self::INI_DIR . '/80-apcu--temp.ini', "extension=apcu.so\n");
        $fs->shouldReceive('deleteFile')->once()->ordered()->with(self::INI_DIR . '/80-apcu--temp.ini');
        $fs->shouldReceive('writeFile')->once()->ordered()->with(self::INI_DIR . '/80-apcu.ini', "extension=apcu.so\n");
        $php = self::createPHPInfo();
        $php->shouldReceive('addInstalledExtensions')->once()->with(['apcu']);

        $loaded = (new Enabler($php, $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('apcu'));

        self::assertSame(['apcu'], $loaded);
    }

    /**
     * @return void
     */
    public function testDefaultIniBasenameIsValid()
    {
        self::assertMatchesRegularExpression(Enabler::RX_VALID_INI_BASENAME, Enabler::INI_BASENAME_DEFAULT);
    }

    /**
     * @return void
     */
    public function testCustomIniBasename()
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, "[PHP Modules]\napcu\nCore\njson\n\n[Zend Modules]\n");
        $fs->shouldReceive('writeFile')->once()->ordered()->with(self::INI_DIR . '/z.80_apcu.conf--temp.ini', "extension=apcu.so\n");
        $fs->shouldReceive('deleteFile')->once()->ordered()->with(self::INI_DIR . '/z.80_apcu.conf--temp.ini');
        $fs->shouldReceive('writeFile')->once()->ordered()->with(self::INI_DIR . '/z.80_apcu.conf.ini', "extension=apcu.so\n");
        $php = self::createPHPInfo();
        $php->shouldReceive('addInstalledExtensions')->once()->with(['apcu']);

        $loaded = (new Enabler($php, $fs, new \IPE\Logger(), (new Options())->withIniBasename('z.{priority}_{name}.conf')))->enable(self::createCompiledExtension('apcu'));

        self::assertSame(['apcu'], $loaded);
    }

    /**
     * @return array<array{string}>
     */
    public static function provideInvalidIniBasenames()
    {
        return [
            [''],
            ['{priority}'],
            ['{name}'],
            ['{name}-{priority}'],
            ['{name}-{priority}-{name}'],
            ['{priority}-{name}-{priority}'],
            ['{priority}-{name}-{name}'],
            ['.{priority}-{name}'],
            ['{priority}-{name}.'],
            ['{priority} {name}'],
            ['{priority}-{module}-{name}'],
            ['Compat'],
        ];
    }

    /**
     * @dataProvider provideInvalidIniBasenames
     *
     * @param string $iniBasename
     *
     * @return void
     */
    public function testInvalidIniBasename($iniBasename)
    {
        $this->expectException(Exception\InvalidArgument::class);
        (new Options())->withIniBasename($iniBasename);
    }

    /**
     * @return void
     */
    public function testModuleListedBothAsPHPAndZendModule()
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, "[PHP Modules]\nblackfire\nCore\njson\n\n[Zend Modules]\nblackfire\n");
        $fs->shouldReceive('writeFile');
        $fs->shouldReceive('deleteFile');
        $php = self::createPHPInfo();
        $php->shouldReceive('addInstalledExtensions')->once()->with(['blackfire']);

        self::assertSame(['blackfire'], (new Enabler($php, $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('blackfire')));
    }

    /**
     * @return array<array{\IPE\PHPInfo\SSLLibrary|null, bool}> the SSL library already known, and whether it must be detected
     */
    public static function provideSSLLibraryCases()
    {
        return [
            [null, true],
            [new SSLLibrary(SSLLibrary::TYPE_OPENSSL, '1.1.1w'), false],
        ];
    }

    /**
     * @dataProvider provideSSLLibraryCases
     *
     * @param \IPE\PHPInfo\SSLLibrary|null $knownLibrary
     * @param bool $detect
     *
     * @return void
     */
    public function testEnablingOpensslDetectsTheSSLLibrary($knownLibrary, $detect)
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, "[PHP Modules]\nCore\njson\nopenssl\n\n[Zend Modules]\n");
        $fs->shouldReceive('writeFile');
        $fs->shouldReceive('deleteFile');
        $php = self::createPHPInfo();
        $php->shouldReceive('addInstalledExtensions')->once()->with(['openssl']);
        $php->shouldReceive('getSSLLibrary')->andReturn($knownLibrary);
        $php->shouldReceive('detectSSLLibrary')->times($detect ? 1 : 0)->with($fs)->andReturnSelf();

        self::assertSame(['openssl'], (new Enabler($php, $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('openssl')));
    }

    /**
     * @return array<array{bool, int|null, string}>
     */
    public static function provideKnownExtensionPriorities()
    {
        return [
            [true, null, 'xx-php-ext-http'],
            [false, null, '90-http'],
            // The priority declared by the maintainers wins
            [true, 30, 'docker-php-ext-http'],
            [false, 30, '30-http'],
        ];
    }

    /**
     * @dataProvider provideKnownExtensionPriorities
     *
     * @param bool $compat
     * @param int|null $manifestPriority
     * @param string $expectedBasename
     *
     * @return void
     */
    public function testKnownExtensionPriority($compat, $manifestPriority, $expectedBasename)
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, "[PHP Modules]\nCore\nhttp\njson\n\n[Zend Modules]\n");
        $fs->shouldReceive('writeFile')->once()->ordered()->with(self::INI_DIR . "/{$expectedBasename}--temp.ini", "extension=http.so\n");
        $fs->shouldReceive('deleteFile')->once()->ordered()->with(self::INI_DIR . "/{$expectedBasename}--temp.ini");
        $fs->shouldReceive('writeFile')->once()->ordered()->with(self::INI_DIR . "/{$expectedBasename}.ini", "extension=http.so\n");
        $php = self::createPHPInfo();
        $php->shouldReceive('addInstalledExtensions')->once()->with(['http']);
        $manifest = null;
        if ($manifestPriority !== null) {
            $manifest = (new Manifest(Manifest::TYPE_COMPOSER, 'pecl/pecl_http', 'http'))->setPriority($manifestPriority);
        }
        $resolved = new Resolved(new http($php, M::mock(OS::class)), '/tmp/ipe/src', '1.0.0', Resolved::ORIGIN_COMPOSER, $manifest);
        $compiled = new CompiledExtension($resolved, 'http', '/usr/local/lib/php/extensions/no-debug-non-zts-20240924/http.so', false);

        self::assertSame(['http'], (new Enabler($php, $fs, new \IPE\Logger(), (new Options())->withIniBasename($compat ? Enabler::INI_BASENAME_COMPAT : Enabler::INI_BASENAME_DEFAULT)))->enable($compiled));
    }

    /**
     * @return array<array{int, string}>
     */
    public static function provideManifestPrioritiesWithCompatIniBasename()
    {
        return [
            [10, 'docker-php-ext-opcache'],
            [Manifest::DEFAULT_PRIORITY, 'docker-php-ext-opcache'],
            [Manifest::DEFAULT_PRIORITY + 1, 'xx-php-ext-opcache'],
        ];
    }

    /**
     * @dataProvider provideManifestPrioritiesWithCompatIniBasename
     *
     * @param int $priority
     * @param string $expectedBasename
     *
     * @return void
     */
    public function testCompatIniBasenameWithManifestPriority($priority, $expectedBasename)
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, "[PHP Modules]\nCore\njson\n\n[Zend Modules]\nZend OPcache\n");
        $fs->shouldReceive('writeFile')->once()->ordered()->with(self::INI_DIR . "/{$expectedBasename}--temp.ini", "zend_extension=opcache.so\n");
        $fs->shouldReceive('deleteFile')->once()->ordered()->with(self::INI_DIR . "/{$expectedBasename}--temp.ini");
        $fs->shouldReceive('writeFile')->once()->ordered()->with(self::INI_DIR . "/{$expectedBasename}.ini", "zend_extension=opcache.so\n");
        $php = self::createPHPInfo();
        $php->shouldReceive('addInstalledExtensions')->once()->with(['Zend OPcache']);
        $manifest = new Manifest(Manifest::TYPE_PECL, 'opcache', 'opcache');
        $manifest->setPriority($priority);

        $loaded = (new Enabler($php, $fs, new \IPE\Logger(), (new Options())->withIniBasename(Enabler::INI_BASENAME_COMPAT)))->enable(self::createCompiledExtension('opcache', true, $manifest));

        self::assertSame(['Zend OPcache'], $loaded);
    }

    /**
     * @return void
     */
    public function testZendExtensionOnOldPHPUsesTheFullPath()
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, "[PHP Modules]\nCore\njson\n\n[Zend Modules]\nXdebug\n");
        $fs->shouldReceive('writeFile')->with(M::type('string'), "zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20240924/xdebug.so\n")->twice();
        $fs->shouldReceive('deleteFile')->once();
        $php = self::createPHPInfo(701);
        $php->shouldReceive('addInstalledExtensions')->once();
        $manifest = new Manifest(Manifest::TYPE_PECL, 'xdebug', 'xdebug');
        $manifest->setPriority(99);

        $loaded = (new Enabler($php, $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('xdebug', true, $manifest));

        self::assertSame(['Xdebug'], $loaded);
    }

    /**
     * @return void
     */
    public function testTimezoneOnPHP5()
    {
        $fs = self::createFilesystem();
        $options = ['-d', 'display_startup_errors=1', '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-d', 'date.timezone=UTC'];
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('/usr/local/bin/php', array_merge($options, ['-m']))))->andReturn([0, self::MODULES_BEFORE], [0, "[PHP Modules]\nCore\njson\nseaslog\n\n[Zend Modules]\n"]);
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('/usr/local/bin/php', array_merge($options, ['-r', 'return;']))->withEnvironment(['LD_BIND_NOW' => '1'])))->andReturn([0, ''], [0, '']);
        $fs->shouldReceive('writeFile');
        $fs->shouldReceive('deleteFile');
        $php = self::createPHPInfo(506);
        $php->shouldReceive('addInstalledExtensions')->once()->with(['seaslog']);

        self::assertSame(['seaslog'], (new Enabler($php, $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('seaslog')));
    }

    /**
     * @return void
     */
    public function testDisableEnabledExtensions()
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, "[PHP Modules]\napcu\nCore\njson\n\n[Zend Modules]\n");
        $iniFile = self::INI_DIR . '/docker-php-ext-apcu.ini';
        $fs->shouldReceive('writeFile')->once()->ordered()->with(self::INI_DIR . '/docker-php-ext-apcu--temp.ini', "extension=apcu.so\n");
        $fs->shouldReceive('deleteFile')->once()->ordered()->with(self::INI_DIR . '/docker-php-ext-apcu--temp.ini');
        $fs->shouldReceive('writeFile')->once()->ordered()->with($iniFile, "extension=apcu.so\n");
        $fs->shouldReceive('writeFile')->once()->ordered()->with($iniFile, ";extension=apcu.so\n");
        $fs->shouldReceive('escapeShellArg')->with($iniFile)->andReturn($iniFile);
        $fs->shouldReceive('writeFile')->once()->ordered()->with(
            '/usr/local/bin/docker-php-ext-enable-apcu',
            "#!/bin/sh\n\nif grep -q -e '^;extension=' -e '^;zend_extension=' {$iniFile}; then\n\techo 'Enabling the extension apcu'\n\tsed -i -e 's/^;extension=/extension=/' -e 's/^;zend_extension=/zend_extension=/' {$iniFile}\nelse\n\techo 'The extension apcu has already been enabled'\nfi\n"
        );
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('chmod', ['+x', '/usr/local/bin/docker-php-ext-enable-apcu'])))->andReturn([0, '']);
        $php = self::createPHPInfo();
        $php->shouldReceive('addInstalledExtensions')->once()->with(['apcu']);

        $enabler = new Enabler($php, $fs, new \IPE\Logger(), (new Options())->withCreateEnableScripts(true)->withIniBasename(Enabler::INI_BASENAME_COMPAT));
        self::assertSame(['apcu'], $enabler->enable(self::createCompiledExtension('apcu')));
        self::assertSame(['apcu' => '/usr/local/bin/docker-php-ext-enable-apcu'], $enabler->disableEnabledExtensions());
    }

    /**
     * @return void
     */
    public function testDisableZendExtensionKeepsTheSettings()
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, "[PHP Modules]\nCore\njson\n\n[Zend Modules]\nXdebug\n");
        $iniFile = self::INI_DIR . '/80-xdebug.ini';
        $fs->shouldReceive('writeFile')->once()->with(self::INI_DIR . '/80-xdebug--temp.ini', "zend_extension=xdebug.so\n");
        $fs->shouldReceive('deleteFile')->once();
        $fs->shouldReceive('writeFile')->once()->ordered()->with($iniFile, "zend_extension=xdebug.so\n");
        $fs->shouldReceive('writeFile')->once()->ordered()->with($iniFile, ";zend_extension=xdebug.so\n");
        $php = self::createPHPInfo();
        $php->shouldReceive('addInstalledExtensions')->once();

        $enabler = new Enabler($php, $fs, new \IPE\Logger(), new Options());
        $enabler->enable(self::createCompiledExtension('xdebug', true));
        // No scripts unless asked in the constructor
        self::assertSame([], $enabler->disableEnabledExtensions());
    }

    /**
     * @return array<array{string, bool, string}>
     */
    public static function provideToggleIniFileCases()
    {
        return [
            ["extension=gd.so\nfoo=1\n", true, ";extension=gd.so\nfoo=1\n"],
            [";extension=gd.so\nfoo=1\n", false, "extension=gd.so\nfoo=1\n"],
            ["\n\tzend_extension = xdebug.so\n", true, "\n\t;zend_extension = xdebug.so\n"],
            ["\n  ; zend_extension = xdebug.so\n;xdebug.mode=debug\n", false, "\n  zend_extension = xdebug.so\n;xdebug.mode=debug\n"],
        ];
    }

    /**
     * @dataProvider provideToggleIniFileCases
     *
     * @param string $content
     * @param bool $active
     * @param string $toggledContent
     *
     * @return void
     */
    public function testToggleIniFile($content, $active, $toggledContent)
    {
        $iniFile = self::INI_DIR . '/foo.ini';
        foreach ([true, false] as $enable) {
            $fs = self::createFilesystem();
            $fs->shouldReceive('isFile')->with($iniFile)->andReturn(true);
            $fs->shouldReceive('readFile')->with($iniFile)->andReturn($content);
            $changes = $enable !== $active;
            if ($changes) {
                $fs->shouldReceive('writeFile')->once()->with($iniFile, $toggledContent);
            } else {
                $fs->shouldNotReceive('writeFile');
            }
            $enabler = new Enabler(self::createPHPInfo(), $fs, new \IPE\Logger(), new Options());
            self::assertSame($changes, $enable ? $enabler->enableIniFile($iniFile) : $enabler->disableIniFile($iniFile));
        }
    }

    /**
     * @return void
     */
    public function testToggleInvalidIniFile()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with('/missing.ini')->andReturn(false);
        $fs->shouldReceive('isFile')->with('/settings.ini')->andReturn(true);
        $fs->shouldReceive('readFile')->with('/settings.ini')->andReturn("memory_limit=1G\n;extension=\n");
        $enabler = new Enabler(self::createPHPInfo(), $fs, new \IPE\Logger(), new Options());
        try {
            $enabler->enableIniFile('/missing.ini');
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertSame('The file /missing.ini does not exist', $x->getMessage());
        }
        try {
            $enabler->disableIniFile('/settings.ini');
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertSame('The file /settings.ini does not contain extension= or zend_extension= lines', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testModuleThatDoesNotLoad()
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, "PHP Warning:  PHP Startup: Unable to load dynamic library 'broken.so' (tried: ...)\n" . self::MODULES_BEFORE);
        $fs->shouldReceive('writeFile')->once()->with(self::INI_DIR . '/80-broken--temp.ini', "extension=broken.so\n");
        $fs->shouldReceive('deleteFile')->once()->with(self::INI_DIR . '/80-broken--temp.ini');
        $php = self::createPHPInfo();
        try {
            (new Enabler($php, $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('broken'));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('broken does not load', $x->getMessage());
            self::assertStringContainsString('Unable to load dynamic library', $x->getMessage());
        }
    }

    /**
     * @return array<array{string, bool}> the error, and whether the hint about the Zend signals is expected
     */
    public static function provideZendSignalsHint()
    {
        return [
            ['undefined symbol: zend_signal_globals_offset', true],
            ['undefined symbol: something_else', false],
        ];
    }

    /**
     * @dataProvider provideZendSignalsHint
     *
     * @param string $error
     * @param bool $hintExpected
     *
     * @return void
     */
    public function testZendSignalsHint($error, $hintExpected)
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, "Warning: Failed loading Zend extension: {$error}\n" . self::MODULES_BEFORE);
        $fs->shouldReceive('writeFile')->once()->with(self::INI_DIR . '/80-broken--temp.ini', "extension=broken.so\n");
        $fs->shouldReceive('deleteFile')->once()->with(self::INI_DIR . '/80-broken--temp.ini');
        try {
            (new Enabler(self::createPHPInfo(), $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('broken'));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString($error, $x->getMessage());
            self::assertSame($hintExpected, strpos($x->getMessage(), '--enable-zend-signals') !== false);
        }
    }

    /**
     * @return void
     */
    public function testModuleThatChangesTheOutputOfPHP()
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, self::MODULES_BEFORE . "noisy\n", '', "Warning: PHP Startup: noisy is not happy\n");
        $fs->shouldReceive('writeFile')->once()->with(self::INI_DIR . '/80-noisy--temp.ini', "extension=noisy.so\n");
        $fs->shouldReceive('deleteFile')->once()->with(self::INI_DIR . '/80-noisy--temp.ini');
        $php = self::createPHPInfo();
        try {
            (new Enabler($php, $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('noisy'));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('noisy loads, but it changes the output of PHP', $x->getMessage());
            self::assertStringContainsString('noisy is not happy', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testModuleWithMissingSymbols()
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, self::MODULES_BEFORE . "geos\n", '', "PHP Warning:  PHP Startup: Unable to load dynamic library 'geos.so' - geos.so: undefined symbol: GEOSMinimumRotatedRectangle_r in Unknown on line 0\n");
        $fs->shouldReceive('writeFile')->once()->with(self::INI_DIR . '/80-geos--temp.ini', "extension=geos.so\n");
        $fs->shouldReceive('deleteFile')->once()->with(self::INI_DIR . '/80-geos--temp.ini');
        $php = self::createPHPInfo();
        try {
            (new Enabler($php, $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('geos'));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('geos uses functions that are not available', $x->getMessage());
            self::assertStringContainsString('GEOSMinimumRotatedRectangle_r', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testMissingSymbolsAlreadyPresentAreIgnored()
    {
        $warning = "PHP Warning:  PHP Startup: Unable to load dynamic library 'other.so' - other.so: undefined symbol: something in Unknown on line 0\n";
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, self::MODULES_BEFORE . "noisy\n", $warning, "{$warning}Warning: PHP Startup: noisy is not happy\n");
        $fs->shouldReceive('writeFile')->once()->with(self::INI_DIR . '/80-noisy--temp.ini', "extension=noisy.so\n");
        $fs->shouldReceive('deleteFile')->once()->with(self::INI_DIR . '/80-noisy--temp.ini');
        $php = self::createPHPInfo();
        try {
            (new Enabler($php, $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('noisy'));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('noisy loads, but it changes the output of PHP', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testModuleThatMakesPHPExitWithAnError()
    {
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, self::MODULES_BEFORE . "crashing\n", '', '', 139);
        $fs->shouldReceive('writeFile')->once()->with(self::INI_DIR . '/80-crashing--temp.ini', "extension=crashing.so\n");
        $fs->shouldReceive('deleteFile')->once()->with(self::INI_DIR . '/80-crashing--temp.ini');
        $php = self::createPHPInfo();
        try {
            (new Enabler($php, $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('crashing'));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('crashing loads, but PHP exits with the code 139', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testOutputOfPHPAlreadyPresentIsIgnored()
    {
        $warning = "Warning: PHP Startup: something else is not happy\n";
        $fs = self::createFilesystem();
        self::expectModuleListings($fs, self::MODULES_BEFORE . "apcu\n", $warning, $warning);
        $fs->shouldReceive('writeFile')->once()->with(self::INI_DIR . '/80-apcu--temp.ini', "extension=apcu.so\n");
        $fs->shouldReceive('deleteFile')->once()->with(self::INI_DIR . '/80-apcu--temp.ini');
        $fs->shouldReceive('writeFile')->once()->with(self::INI_DIR . '/80-apcu.ini', "extension=apcu.so\n");
        $php = self::createPHPInfo();
        $php->shouldReceive('addInstalledExtensions')->once()->with(['apcu']);
        self::assertSame(['apcu'], (new Enabler($php, $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('apcu')));
    }

    /**
     * @return void
     */
    public function testNumbersInTheOutputOfPHPAreIgnored()
    {
        $fs = self::createFilesystem();
        self::expectModuleListings(
            $fs,
            self::MODULES_BEFORE . "apcu\n",
            "[ddtrace] [error] [485757] Found incompatible Xdebug version 2.6.1\nStartup took 2 ms\n",
            "[ddtrace] [error] [488714] Found incompatible Xdebug version 2.6.1\nStartup took 1.5 ms\n"
        );
        $fs->shouldReceive('writeFile')->once()->with(self::INI_DIR . '/80-apcu--temp.ini', "extension=apcu.so\n");
        $fs->shouldReceive('deleteFile')->once()->with(self::INI_DIR . '/80-apcu--temp.ini');
        $fs->shouldReceive('writeFile')->once()->with(self::INI_DIR . '/80-apcu.ini', "extension=apcu.so\n");
        $php = self::createPHPInfo();
        $php->shouldReceive('addInstalledExtensions')->once()->with(['apcu']);
        self::assertSame(['apcu'], (new Enabler($php, $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('apcu')));
    }

    /**
     * @return void
     */
    public function testNoIniScanDir()
    {
        $fs = self::createFilesystem();
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getIniScanDir')->andReturn('');
        try {
            (new Enabler($php, $fs, new \IPE\Logger(), new Options()))->enable(self::createCompiledExtension('apcu'));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('ini', $x->getMessage());
        }
    }
}
