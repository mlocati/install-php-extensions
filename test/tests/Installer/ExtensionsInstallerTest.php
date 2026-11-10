<?php

namespace IPE\Test\Installer;

use IPE\Exception;
use IPE\Extension;
use IPE\Extension\Manifest;
use IPE\Extension\Resolved;
use IPE\Filesystem;
use IPE\Installer\BinaryInstaller;
use IPE\Installer\BuildOptions;
use IPE\Installer\BuildTools;
use IPE\Installer\Compiler;
use IPE\Installer\Enabler;
use IPE\Installer\ExtensionsInstaller;
use IPE\Installer\Report\InstalledExtension;
use IPE\Installer\State;
use IPE\Installer\VendorArchivePreparer;
use IPE\Installer\ZendExtensionDetector;
use IPE\Option\Validator;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\PHPSystemPackage;
use IPE\SystemDependency\Registry;
use IPE\SystemPackager\Package;
use IPE\Test\Support\DependencyInstallerTest\FakePackager;
use IPE\Test\Support\ResolverTest\FakeBinaryExtension;
use IPE\Test\Support\SystemDependencyTest\FakeLibrary;
use IPE\Test\Support\SystemDependencyTest\FakeThirdPartyLibrary;
use IPE\Test\Support\SystemDependencyTest\FakeTool;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ExtensionsInstallerTest extends TestCase
{
    /**
     * @var \IPE\Test\Support\DependencyInstallerTest\FakePackager
     */
    private $packager;

    /**
     * @var \IPE\Test\Support\SystemDependencyTest\FakeTool
     */
    private $tool;

    /**
     * @var \IPE\PHPInfo&\Mockery\MockInterface
     */
    private $php;

    /**
     * @var \IPE\Extension\Asked\Resolver&\Mockery\MockInterface
     */
    private $resolver;

    /**
     * @var array<string, string>
     */
    private $writtenFiles = [];

    /**
     * @return \IPE\Filesystem&\Mockery\MockInterface
     */
    private function createFilesystem()
    {
        $fs = M::mock(Filesystem::class)->shouldIgnoreMissing();
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('resolveCommand')->andReturn('');
        $fs->shouldReceive('writeFile')->andReturnUsing(function ($path, $content) {
            if (is_string($path) && is_string($content)) {
                $this->writtenFiles[$path] = $content;
            }
        });
        $fs->shouldReceive('readFile')->andReturnUsing(function ($path) {
            return is_string($path) && isset($this->writtenFiles[$path]) ? $this->writtenFiles[$path] : '';
        });

        return $fs;
    }

    /**
     * @param \IPE\SystemPackager\Package|null $phpPackage the system package that installed PHP
     *
     * @return \IPE\Installer\ExtensionsInstaller
     */
    private function createInstaller(Filesystem $fs, $phpPackage = null)
    {
        $expansions = [];
        foreach (['libfake', 'libfake-dev', 'fake-tool-pkg', 'autoconf', 'gcc'] as $package) {
            $expansions[$package] = [$package];
        }
        $expansions['php8.4-dev=8.4.27-1~deb13u1'] = ['php8.4-dev'];
        $this->packager = new FakePackager(M::mock(OS::class), [], $expansions);
        $this->packager->owningPackage = $phpPackage;
        $this->php = M::mock(PHPInfo::class)->shouldIgnoreMissing();
        $this->php->shouldReceive('getExtensionDir')->andReturn('/ext');
        $this->php->shouldReceive('getIniScanDir')->andReturn('/ini');
        $this->php->shouldReceive('getIniScanDirs')->andReturn(['/ini', '/app-ini']);
        $this->php->shouldReceive('getPHPBin')->andReturn('/usr/bin/php');
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getSystemPackager')->andReturn($this->packager);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $this->tool = new FakeTool($this->php, $os, new Values());
        $registry = new Registry(self::createApp(), new Validator(new \IPE\Logger()), new \IPE\Logger());
        $registry->registerDependency($this->tool);
        $registry->registerDependency(new FakeLibrary($this->php, $os, new Values()));
        $registry->registerDependency(new FakeThirdPartyLibrary($this->php, $os, new Values()));
        $buildTools = new BuildTools($this->php, $fs, new \IPE\Logger());
        $buildOptions = new BuildOptions();
        $this->resolver = M::mock(Extension\Asked\Resolver::class);

        return new ExtensionsInstaller(
            $os,
            $this->php,
            $registry,
            new Compiler($buildTools, $buildOptions, $this->php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()),
            new BinaryInstaller($this->php, $fs, $buildTools, $buildOptions),
            new VendorArchivePreparer(M::mock(Filesystem\ArchiveExtractor::class), $fs, new BuildOptions()),
            new Enabler($this->php, $fs, new \IPE\Logger(), new Enabler\Options()),
            $this->resolver,
            new PHPSystemPackage($this->php, $os),
            new \IPE\Logger()
        );
    }

    /**
     * @param bool $fails
     *
     * @return \IPE\Extension\Resolved
     */
    private static function createResolvedVendorInstaller($fails = false)
    {
        $extension = M::mock(Extension::class . ', ' . Extension\ProvidesBinaryInstaller::class . ', ' . Extension\RequiresSystemDependencies::class);
        $extension->shouldReceive('getHandle')->andReturn('vendor');
        $extension->shouldReceive('getInstalledNames')->andReturn(['vendor']);
        $extension->shouldReceive('getRequiredSystemDependencies')->andReturn([FakeTool::getHandle()]);
        $expectation = $extension->shouldReceive('runBinaryInstaller')->once();
        if ($fails) {
            $expectation->andThrow(new Exception\IO('Failed'));
        }

        return new Resolved($extension, '/src', '', Resolved::ORIGIN_BINARY, null, new Values());
    }

    /**
     * @return \IPE\Extension\Resolved
     */
    private function createResolvedBinaryExtension(Filesystem $fs)
    {
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);

        return new Resolved(new FakeBinaryExtension($this->php, $os, M::mock(Filesystem\Downloader::class), 'module.so'), '/src', '', Resolved::ORIGIN_BINARY);
    }

    /**
     * @return void
     */
    public function testBinaryExtensionDoesNotNeedPhpize()
    {
        $fs = $this->createFilesystem();
        $fs->shouldReceive('run')->andReturn([0, "[PHP Modules]\nvendor\n"]);
        $installer = $this->createInstaller($fs);
        $installer->install([self::createResolvedVendorInstaller()], new State($this->php, $fs));
        self::assertSame(['fake-tool-pkg'], $this->packager->installed);
        self::assertSame(['install', 'finalize'], $this->tool->log);
        self::assertContains('remove fake-tool-pkg', $this->packager->log);
        self::assertContains('delete-cache', $this->packager->log);
    }

    /**
     * @return void
     */
    public function testCleanupAfterFailure()
    {
        $fs = $this->createFilesystem();
        $installer = $this->createInstaller($fs);
        try {
            $installer->install([self::createResolvedVendorInstaller(true)], new State($this->php, $fs), true, false);
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertSame('Failed', $x->getMessage());
        }
        self::assertSame(['install'], $this->tool->log);
        self::assertContains('remove fake-tool-pkg', $this->packager->log);
        self::assertNotContains('delete-cache', $this->packager->log);
    }

    /**
     * @return void
     */
    public function testCompilingNeedsPhpizeAndTheLibrariesOfTheManifest()
    {
        $fs = $this->createFilesystem();
        $fs->shouldReceive('isFile')->andReturn(false);
        $fs->shouldReceive('listDirectoryContents')->andReturn([]);
        $installer = $this->createInstaller($fs);
        $manifest = (new Manifest(Manifest::TYPE_PECL, 'foo', 'foo'))->addRequiredLibrary('fake');
        $resolved = new Resolved(null, '/src', '', Resolved::ORIGIN_URL, $manifest);
        try {
            $installer->install([$resolved], new State($this->php, $fs));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('config.m4', $x->getMessage());
        }
        self::assertEqualsCanonicalizing(['libfake', 'libfake-dev', 'fake-tool-pkg', 'autoconf', 'gcc'], $this->packager->installed);
        self::assertContains('remove fake-tool-pkg libfake-dev autoconf gcc', $this->packager->log);
    }

    /**
     * @return void
     */
    public function testCompilingWithSystemPHPNeedsItsDevelopmentPackage()
    {
        $fs = $this->createFilesystem();
        $fs->shouldReceive('isFile')->andReturn(false);
        $fs->shouldReceive('listDirectoryContents')->andReturn([]);
        $installer = $this->createInstaller($fs, new Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4'));
        $resolved = new Resolved(null, '/src', '', Resolved::ORIGIN_URL, new Manifest(Manifest::TYPE_PECL, 'foo', 'foo'));
        try {
            $installer->install([$resolved], new State($this->php, $fs));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('config.m4', $x->getMessage());
        }
        self::assertContains('install autoconf=1.0 gcc=1.0 php8.4-dev=1.0', $this->packager->log);
        self::assertContains('remove autoconf gcc php8.4-dev', $this->packager->log);
    }

    /**
     * @return array<array{array<string, array<string, string|true>>, string}>
     */
    public static function provideInvalidOptionCases()
    {
        return [
            [[FakeThirdPartyLibrary::getHandle() => ['foo' => true]], "does not accept the option 'foo'"],
            [['lib-unknown' => ['foo' => true]], 'unknown system dependencies: lib-unknown'],
        ];
    }

    /**
     * @dataProvider provideInvalidOptionCases
     *
     * @param array<string, array<string, string|true>> $options
     * @param string $expectedMessage
     *
     * @return void
     */
    public function testInvalidOptionsAreDetectedBeforeInstallingAnything(array $options, $expectedMessage)
    {
        $fs = $this->createFilesystem();
        $installer = $this->createInstaller($fs);
        try {
            $installer->install([], (new State($this->php, $fs))->withSystemDependencyOptions($options));
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidOptionValues $x) {
            self::assertStringContainsString($expectedMessage, $x->getMessage());
        }
        self::assertSame([], $this->packager->log);
    }

    /**
     * @return void
     */
    public function testOptionsOfThirdPartyDependenciesAreAccepted()
    {
        $fs = $this->createFilesystem();
        $installer = $this->createInstaller($fs);
        $installer->install([], (new State($this->php, $fs))->withSystemDependencyOptions([FakeThirdPartyLibrary::getHandle() => ['edition' => 'pro']]));
        self::assertContains('delete-cache', $this->packager->log);
    }

    /**
     * @return void
     */
    public function testExtensionsAreNotDisabledIfTheInstallationFails()
    {
        $fs = $this->createFilesystem();
        $fs->shouldReceive('isFile')->andReturn(true);
        $fs->shouldReceive('run')->andReturn([0, "[PHP Modules]\nCore\n"], [0, "[PHP Modules]\nCore\nfakebinary\n"]);
        $installer = $this->createInstaller($fs);
        $resolved = $this->createResolvedBinaryExtension($fs);
        try {
            $installer->install([$resolved, self::createResolvedVendorInstaller(true)], new State($this->php, $fs), false);
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertSame('Failed', $x->getMessage());
        }
        self::assertStringStartsWith('extension=', $this->writtenFiles['/ini/' . Manifest::DEFAULT_PRIORITY . '-fakebinary.ini']);
    }

    /**
     * @return array<array{bool, string, string}>
     */
    public static function provideEnableCases()
    {
        return [
            [true, 'extension=', ''],
            [false, ';extension=', ''],
        ];
    }

    /**
     * @dataProvider provideEnableCases
     *
     * @param bool $enable
     * @param string $expectedIniPrefix
     * @param string $expectedEnableScript
     *
     * @return void
     */
    public function testEnable($enable, $expectedIniPrefix, $expectedEnableScript)
    {
        $fs = $this->createFilesystem();
        $fs->shouldReceive('isFile')->andReturn(true);
        $fs->shouldReceive('run')->andReturn([0, "[PHP Modules]\nCore\n"], [0, "[PHP Modules]\nCore\nfakebinary\n"]);
        $installer = $this->createInstaller($fs);
        $resolved = $this->createResolvedBinaryExtension($fs);
        $report = $installer->install([$resolved], new State($this->php, $fs), $enable);
        $iniFile = '/ini/' . Manifest::DEFAULT_PRIORITY . '-fakebinary.ini';
        self::assertStringStartsWith($expectedIniPrefix, $this->writtenFiles[$iniFile]);
        self::assertCount(1, $report->getExtensions());
        $installed = $report->getExtensions()[0];
        self::assertSame(InstalledExtension::METHOD_BINARY, $installed->getMethod());
        self::assertSame(['/ext/fakebinary.so'], $installed->getModuleFiles());
        self::assertFalse($installed->isZendExtension());
        self::assertSame([$iniFile => $enable], $installed->getIniFiles());
        self::assertSame(['fakebinary'], $installed->getLoadedModules());
        self::assertSame($expectedEnableScript, $installed->getEnableScript());
        self::assertSame([], $installed->getConfigureArguments());
        self::assertSame([], $report->getCompiledLibraries());
    }

    /**
     * @return list<array{bool, string}>
     */
    public static function provideReenableCases()
    {
        return [
            [true, "extension=fake.so\n"],
            [false, ";extension=fake.so\n"],
        ];
    }

    /**
     * @dataProvider provideReenableCases
     *
     * @param bool $enable
     * @param string $expectedIniContent
     *
     * @return void
     */
    public function testDisabledExtensionIsEnabledWithoutInstallingAnything($enable, $expectedIniContent)
    {
        $iniFile = '/ini/80-fake.ini';
        $this->writtenFiles[$iniFile] = ";extension=fake.so\n";
        $fs = $this->createFilesystem();
        $fs->shouldReceive('isFile')->andReturn(true);
        $fs->shouldReceive('run')->andReturn([0, "[PHP Modules]\nCore\n"], [0, "[PHP Modules]\nCore\nfake\n"]);
        $installer = $this->createInstaller($fs);
        $extension = M::mock(Extension::class . ', ' . Extension\RequiresSystemDependencies::class);
        $extension->shouldReceive('getHandle')->andReturn('fake');
        $extension->shouldReceive('getInstalledNames')->andReturn(['fake']);
        $extension->shouldNotReceive('getRequiredSystemDependencies');
        $report = $installer->install([new Resolved($extension, $iniFile, '', Resolved::ORIGIN_DISABLED)], new State($this->php, $fs), $enable);
        self::assertSame([], $this->packager->installed);
        self::assertSame($expectedIniContent, $this->writtenFiles[$iniFile]);
        $installed = $report->getExtensions()[0];
        self::assertSame(InstalledExtension::METHOD_ENABLED, $installed->getMethod());
        self::assertSame([$iniFile => $enable], $installed->getIniFiles());
        self::assertSame(['fake'], $installed->getLoadedModules());
    }

    /**
     * @dataProvider provideReenableCases
     *
     * @param bool $enable
     * @param string $expectedIniContent
     *
     * @return void
     */
    public function testSystemPackage($enable, $expectedIniContent)
    {
        $iniFile = '/ini/20-fake.ini';
        $this->writtenFiles[$iniFile] = "extension=fake.so\n";
        $fs = $this->createFilesystem();
        $fs->shouldReceive('isFile')->andReturn(true);
        $fs->shouldReceive('isDirectory')->andReturn(true);
        $fs->shouldReceive('listDirectoryContents')->andReturnUsing(static function ($path) {
            return $path === '/ini' ? ['20-fake.ini'] : [];
        });
        $fs->shouldReceive('run')->andReturn([0, "[PHP Modules]\nCore\nfake\n"]);
        $installer = $this->createInstaller($fs, new Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4'));
        $this->packager->expansions['php8.4-fake=8.4.27-1~deb13u1'] = ['php8.4-fake'];
        $extension = M::mock(Extension::class . ', ' . Extension\RequiresSystemDependencies::class);
        $extension->shouldReceive('getHandle')->andReturn('fake');
        $extension->shouldReceive('getInstalledNames')->andReturn(['fake']);
        $extension->shouldNotReceive('getRequiredSystemDependencies');
        $report = $installer->install([new Resolved($extension, 'php8.4-fake=8.4.27-1~deb13u1', '8.4.27', Resolved::ORIGIN_SYSTEM_PACKAGE)], new State($this->php, $fs), $enable);
        // Without phpize and the development package
        self::assertSame(['php8.4-fake'], $this->packager->installed);
        self::assertNotContains('remove php8.4-fake', $this->packager->log);
        self::assertSame($expectedIniContent, $this->writtenFiles[$iniFile]);
        $installed = $report->getExtensions()[0];
        self::assertSame(InstalledExtension::METHOD_SYSTEM_PACKAGE, $installed->getMethod());
        self::assertSame([$iniFile => $enable], $installed->getIniFiles());
        self::assertSame(['fake'], $installed->getLoadedModules());
    }

    /**
     * @dataProvider provideReenableCases
     *
     * @param bool $enable
     * @param string $expectedIniContent
     *
     * @return void
     */
    public function testSystemPackageWithDisabledIniFile($enable, $expectedIniContent)
    {
        $iniFile = '/ini/20-fake.ini';
        $this->writtenFiles[$iniFile] = ";extension=fake.so\n";
        $fs = $this->createFilesystem();
        $fs->shouldReceive('isFile')->andReturn(true);
        $fs->shouldReceive('isDirectory')->andReturn(true);
        $fs->shouldReceive('listDirectoryContents')->andReturnUsing(static function ($path) {
            return $path === '/ini' ? ['20-fake.ini'] : [];
        });
        $installer = $this->createInstaller($fs, new Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4'));
        $this->packager->expansions['php8.4-fake=8.4.27-1~deb13u1'] = ['php8.4-fake'];
        $fs->shouldReceive('run')->andReturnUsing(function () use ($iniFile) {
            return [0, "[PHP Modules]\nCore\n" . ($this->writtenFiles[$iniFile] === "extension=fake.so\n" ? "fake\n" : '')];
        });
        $extension = M::mock(Extension::class . ', ' . Extension\RequiresSystemDependencies::class);
        $extension->shouldReceive('getHandle')->andReturn('fake');
        $extension->shouldReceive('getInstalledNames')->andReturn(['fake']);
        $extension->shouldNotReceive('getRequiredSystemDependencies');
        $report = $installer->install([new Resolved($extension, 'php8.4-fake=8.4.27-1~deb13u1', '8.4.27', Resolved::ORIGIN_SYSTEM_PACKAGE)], new State($this->php, $fs), $enable);
        self::assertSame($expectedIniContent, $this->writtenFiles[$iniFile]);
        $installed = $report->getExtensions()[0];
        self::assertSame(InstalledExtension::METHOD_SYSTEM_PACKAGE, $installed->getMethod());
        self::assertSame([$iniFile => $enable], $installed->getIniFiles());
        self::assertSame(['fake'], $installed->getLoadedModules());
    }

    /**
     * @return void
     */
    public function testFilesCreatedByTheInstallerOfTheVendor()
    {
        $fs = $this->createFilesystem();
        $fs->shouldReceive('isDirectory')->andReturn(true);
        $fs->shouldReceive('listDirectoryContents')->with('/ext')->andReturn(['other.so'], ['other.so', 'vendor.so', 'vendor.txt']);
        $fs->shouldReceive('listDirectoryContents')->with('/ini')->andReturn(['10-other.ini']);
        $fs->shouldReceive('listDirectoryContents')->with('/app-ini')->andReturn([], ['20-vendor.ini']);
        $fs->shouldReceive('run')->andReturn([0, "[PHP Modules]\nvendor\n"]);
        $this->writtenFiles['/app-ini/20-vendor.ini'] = "; Disabled by the vendor\n;extension=vendor.so\n";
        $installer = $this->createInstaller($fs);
        $skipped = new Extension\Skipped('other', Extension\Skipped::REASON_ALREADY_INSTALLED, 'The extension other is already installed - skipping');
        $report = $installer->install([self::createResolvedVendorInstaller()], (new State($this->php, $fs))->setSkippedExtensions([$skipped]));
        self::assertSame([$skipped], $report->getSkippedExtensions());
        self::assertCount(1, $report->getExtensions());
        $installed = $report->getExtensions()[0];
        self::assertSame(InstalledExtension::METHOD_VENDOR_INSTALLER, $installed->getMethod());
        self::assertSame(['/ext/vendor.so'], $installed->getModuleFiles());
        self::assertNull($installed->isZendExtension());
        self::assertSame(['/app-ini/20-vendor.ini' => false], $installed->getIniFiles());
        self::assertSame(['vendor'], $installed->getLoadedModules());
        self::assertSame(['fake-tool-pkg'], $report->getSystemPackages()->getVolatile());
    }

    /**
     * @return void
     */
    public function testInstallAsked()
    {
        $fs = $this->createFilesystem();
        $fs->shouldReceive('run')->andReturn([0, "[PHP Modules]\nvendor\n"]);
        $installer = $this->createInstaller($fs);
        $asked = [new Extension\Asked\FromHandle('vendor', '')];
        $options = [FakeThirdPartyLibrary::getHandle() => ['edition' => 'pro']];
        $resolved = self::createResolvedVendorInstaller();
        $skipped = new Extension\Skipped('other', Extension\Skipped::REASON_ALREADY_INSTALLED, 'The extension other is already installed - skipping');
        $this->resolver->shouldReceive('resolveAskedExtensions')->once()->andReturnUsing(static function (array $askedExtensions, State $state) use ($asked, $resolved, $skipped) {
            self::assertSame($asked, $askedExtensions);
            self::assertSame(['edition' => 'pro'], $state->getSystemDependencyUserOptions(FakeThirdPartyLibrary::getHandle()));
            $state->addSkippedExtension($skipped);

            return [$resolved];
        });
        $report = $installer->installAsked($asked, $options, true, false);
        self::assertSame([$skipped], $report->getSkippedExtensions());
        self::assertCount(1, $report->getExtensions());
        self::assertSame($resolved, $report->getExtensions()[0]->getResolved());
        self::assertNotContains('delete-cache', $this->packager->log);
    }
}
