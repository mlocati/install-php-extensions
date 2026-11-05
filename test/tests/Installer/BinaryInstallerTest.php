<?php

namespace IPE\Test\Installer;

use IPE\Exception;
use IPE\Extension;
use IPE\Extension\Resolved;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Installer\BinaryInstaller;
use IPE\Installer\BuildOptions;
use IPE\Installer\BuildTools;
use IPE\Installer\CompiledExtension;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\ResolverTest\FakeBinaryExtension;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class BinaryInstallerTest extends TestCase
{
    /**
     * @param string $buildType
     *
     * @return \IPE\Installer\BinaryInstaller
     */
    private static function createInstaller(Filesystem $fs, $buildType = BuildOptions::TYPE_OPTIMIZED)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getExtensionDir')->andReturn('/ext');

        return new BinaryInstaller($php, $fs, new BuildTools($php, $fs, new \IPE\Logger()), (new BuildOptions())->withType($buildType));
    }

    /**
     * @return \IPE\Extension\Resolved
     */
    private static function createResolved(Filesystem $fs)
    {
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);

        return new Resolved(new FakeBinaryExtension(M::mock(PHPInfo::class), $os, M::mock(Filesystem\Downloader::class), 'module-file.so'), '/src', '', Resolved::ORIGIN_BINARY);
    }

    /**
     * @param string $strip the path of the strip command
     *
     * @return \IPE\Filesystem&\Mockery\MockInterface
     */
    private static function createFilesystem($strip = '')
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->andReturn(true);
        $fs->shouldReceive('resolveCommand')->with('strip')->andReturn($strip);

        return $fs;
    }

    /**
     * @param \IPE\Filesystem&\Mockery\MockInterface $fs
     * @param non-empty-string $modulePath
     *
     * @return void
     */
    private static function expectStrip($fs, $modulePath)
    {
        $fs->shouldReceive('getFileSize')->with($modulePath)->andReturn(300000, 100000);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('/usr/bin/strip', ['--strip-all', $modulePath])->withWorkingDirectory('/src')->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
    }

    /**
     * @return void
     */
    public function testInstall()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('copyFile')->once()->with('/src/module-file.so', '/ext/fakebinary.so');
        $resolved = self::createResolved($fs);
        $installed = self::createInstaller($fs)->install($resolved);
        self::assertInstanceOf(CompiledExtension::class, $installed);
        self::assertSame('fakebinary', $installed->getModuleName());
        self::assertSame('/ext/fakebinary.so', $installed->getModulePath());
        self::assertFalse($installed->isZendExtension());
        self::assertSame("fakebinary.setting=1\n", $installed->getIniSettings());
        self::assertSame($resolved, $installed->getResolved());
    }

    /**
     * @return void
     */
    public function testStrip()
    {
        $fs = self::createFilesystem('/usr/bin/strip');
        $fs->shouldReceive('copyFile')->once()->with('/src/module-file.so', '/ext/fakebinary.so');
        self::expectStrip($fs, '/ext/fakebinary.so');
        $resolved = self::createResolved($fs);
        self::assertInstanceOf(CompiledExtension::class, self::createInstaller($fs)->install($resolved));
    }

    /**
     * @return void
     */
    public function testKeepSymbols()
    {
        $fs = self::createFilesystem('/usr/bin/strip');
        $fs->shouldReceive('copyFile')->once()->with('/src/module-file.so', '/ext/fakebinary.so');
        $fs->shouldNotReceive('run');
        $resolved = self::createResolved($fs);
        self::assertInstanceOf(CompiledExtension::class, self::createInstaller($fs, BuildOptions::TYPE_WITH_SYMBOLS)->install($resolved));
    }

    /**
     * @return void
     */
    public function testVendorInstaller()
    {
        $fs = self::createFilesystem('/usr/bin/strip');
        self::expectStrip($fs, '/ext/vendor.so');
        $values = new Values();
        $extension = M::mock(Extension::class . ', ' . Extension\ProvidesBinaryInstaller::class);
        $extension->shouldReceive('getHandle')->andReturn('vendor');
        $extension->shouldReceive('runBinaryInstaller')->once()->with('/src', $values);
        $resolved = new Resolved($extension, '/src', '', Resolved::ORIGIN_BINARY, null, $values);
        self::assertNull(self::createInstaller($fs)->install($resolved));
    }

    /**
     * @return void
     */
    public function testNotBinary()
    {
        $this->expectException(Exception\InvalidArgument::class);
        self::createInstaller(self::createFilesystem())->install(new Resolved(null, '/src', '', Resolved::ORIGIN_URL));
    }
}
