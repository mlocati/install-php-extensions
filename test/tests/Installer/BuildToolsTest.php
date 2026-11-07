<?php

namespace IPE\Test\Installer;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Filesystem\VolatileDirectory;
use IPE\Installer\BuildTools;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class BuildToolsTest extends TestCase
{
    /**
     * @param non-empty-string $phpBin
     *
     * @return \IPE\PHPInfo&\Mockery\MockInterface
     */
    private static function createPHPInfo($phpBin = '/usr/local/bin/php')
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn($phpBin);
        $php->shouldReceive('getVersion')->andReturn('8.4.14');
        $php->shouldReceive('getAPIVersion')->andReturn(20240924);

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
     * @return void
     */
    public function testPhpizeNextToPHPWithWrongAPIIsSkipped()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with('/usr/local/bin/phpize')->andReturn(true);
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('/usr/local/bin/phpize', ['--version'])))->andReturn([0, "Configuring for:\nPHP Api Version:         20230831\nZend Module Api No:      20230831"]);
        $fs->shouldReceive('resolveCommand')->with('phpize')->andReturn('/usr/bin/phpize');
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('/usr/bin/phpize', ['--version'])))->andReturn([0, "Configuring for:\nPHP Api Version:         20240924\nZend Module Api No:      20240924"]);
        $buildTools = new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger());
        self::assertSame('/usr/bin/phpize', $buildTools->getPhpize());
        // The result is cached
        self::assertSame('/usr/bin/phpize', $buildTools->getPhpize());
    }

    /**
     * @return void
     */
    public function testPhpizeNotFound()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with('/usr/local/bin/phpize')->andReturn(false);
        $fs->shouldReceive('resolveCommand')->with('phpize')->andReturn('');
        $buildTools = new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger());
        try {
            $buildTools->getPhpize();
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('phpize', $x->getMessage());
            self::assertStringContainsString('20240924', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testPhpizeWithVersionSuffix()
    {
        // Debian: /usr/bin/phpize is managed by update-alternatives, and it may be the one of another PHP version
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with('/usr/bin/phpize8.4')->andReturn(true);
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('/usr/bin/phpize8.4', ['--version'])))->andReturn([0, 'PHP Api Version:         20240924']);
        self::assertSame('/usr/bin/phpize8.4', (new BuildTools(self::createPHPInfo('/usr/bin/php8.4'), $fs, new \IPE\Logger()))->getPhpize());

        // Alpine: the versioned phpize is missing, the one without suffix matches
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with('/usr/bin/phpize84')->andReturn(false);
        $fs->shouldReceive('resolveCommand')->with('phpize84')->andReturn('');
        $fs->shouldReceive('isFile')->with('/usr/bin/phpize')->andReturn(true);
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('/usr/bin/phpize', ['--version'])))->andReturn([0, 'PHP Api Version:         20240924']);
        self::assertSame('/usr/bin/phpize', (new BuildTools(self::createPHPInfo('/usr/bin/php84'), $fs, new \IPE\Logger()))->getPhpize());

        // Every candidate is checked once
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with('/usr/bin/phpize8.3')->andReturn(true);
        $fs->shouldReceive('resolveCommand')->with('phpize8.3')->andReturn('/usr/bin/phpize8.3');
        $fs->shouldReceive('isFile')->with('/usr/bin/phpize')->andReturn(true);
        $fs->shouldReceive('resolveCommand')->with('phpize')->andReturn('/usr/bin/phpize');
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('/usr/bin/phpize8.3', ['--version'])))->andReturn([0, 'PHP Api Version:         20230831']);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('/usr/bin/phpize', ['--version'])))->andReturn([0, 'PHP Api Version:         20230831']);
        try {
            (new BuildTools(self::createPHPInfo('/usr/bin/php8.3'), $fs, new \IPE\Logger()))->getPhpize();
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('20240924', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testPhpConfigWithVersionSuffix()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with('/usr/bin/php-config8.4')->andReturn(true);
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('/usr/bin/php-config8.4', ['--version'])))->andReturn([0, "8.4.14\n"]);
        self::assertSame('/usr/bin/php-config8.4', (new BuildTools(self::createPHPInfo('/usr/bin/php8.4'), $fs, new \IPE\Logger()))->getPhpConfig());
    }

    /**
     * @return void
     */
    public function testPhpConfig()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with('/usr/local/bin/php-config')->andReturn(true);
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('/usr/local/bin/php-config', ['--version'])))->andReturn([0, "8.4.14\n"]);
        $buildTools = new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger());
        self::assertSame('/usr/local/bin/php-config', $buildTools->getPhpConfig());

        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with('/usr/local/bin/php-config')->andReturn(true);
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('/usr/local/bin/php-config', ['--version'])))->andReturn([0, "8.3.27\n"]);
        $fs->shouldReceive('resolveCommand')->with('php-config')->andReturn('/usr/local/bin/php-config');
        $buildTools = new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger());
        self::assertSame('', $buildTools->getPhpConfig());

        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with('/usr/local/bin/php-config')->andReturn(false);
        $fs->shouldReceive('resolveCommand')->with('php-config')->andReturn('');
        $buildTools = new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger());
        self::assertSame('', $buildTools->getPhpConfig());
    }

    /**
     * @return void
     */
    public function testLibdir()
    {
        $fs = self::createFilesystem();
        $php = self::createPHPInfo();
        $php->shouldReceive('getConfigureCommand')->andReturn("'./configure' '--build=x86_64-linux-gnu' '--with-libdir=lib/x86_64-linux-gnu' '--enable-fpm'");
        self::assertSame('lib/x86_64-linux-gnu', (new BuildTools($php, $fs, new \IPE\Logger()))->getLibdir());

        $php = self::createPHPInfo();
        $php->shouldReceive('getConfigureCommand')->andReturn("'./configure' '--enable-fpm'");
        $php->shouldReceive('getMajorMinorVersion')->andReturn(701);
        self::assertSame('', (new BuildTools($php, $fs, new \IPE\Logger()))->getLibdir());
    }

    /**
     * @return array<array{string, int, string, string}> the path of gcc, its exit code, its output, and the expected libdir
     */
    public static function provideLibdirOnOldPHP()
    {
        return [
            ['/usr/bin/gcc', 0, "x86_64-linux-gnu\n", 'lib/x86_64-linux-gnu'],
            ['/usr/bin/gcc', 0, "\n", ''],
            ['/usr/bin/gcc', 1, "x86_64-linux-gnu\n", ''],
            ['', 0, '', ''],
        ];
    }

    /**
     * @dataProvider provideLibdirOnOldPHP
     *
     * @param string $gcc
     * @param int $rc
     * @param string $output
     * @param string $expected
     *
     * @return void
     */
    public function testLibdirOnOldPHP($gcc, $rc, $output, $expected)
    {
        $php = self::createPHPInfo();
        $php->shouldReceive('getConfigureCommand')->andReturn("'./configure' '--enable-fpm'");
        $php->shouldReceive('getMajorMinorVersion')->andReturn(700);
        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('gcc')->andReturn($gcc);
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('gcc', ['-print-multiarch'])))->andReturn([$rc, $output]);
        self::assertSame($expected, (new BuildTools($php, $fs, new \IPE\Logger()))->getLibdir());
    }

    /**
     * @return void
     */
    public function testLibdirOnOldPHPConfiguredWithIt()
    {
        $php = self::createPHPInfo();
        $php->shouldReceive('getConfigureCommand')->andReturn("'./configure' '--with-libdir=lib64'");
        $php->shouldReceive('getMajorMinorVersion')->andReturn(505);
        self::assertSame('lib64', (new BuildTools($php, self::createFilesystem(), new \IPE\Logger()))->getLibdir());
    }

    /**
     * @return void
     */
    public function testMake()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('make')->andReturn('');
        $fs->shouldReceive('resolveCommand')->with('gmake')->andReturn('/usr/bin/gmake');
        self::assertSame('/usr/bin/gmake', (new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger()))->getMake());

        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('make')->andReturn('');
        $fs->shouldReceive('resolveCommand')->with('gmake')->andReturn('');
        try {
            (new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger()))->getMake();
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('make', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testPkgConfigModule()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('pkg-config')->andReturn('/usr/bin/pkg-config');
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('pkg-config', ['--exists', 'aom'])->withOutput(Command::OUTPUT_DISCARD)))->andReturn([0, '']);
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('pkg-config', ['--exists', 'libavif >= 1.0'])->withOutput(Command::OUTPUT_DISCARD)))->andReturn([1, '']);
        $buildTools = new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger());
        self::assertTrue($buildTools->hasPkgConfigModule('aom'));
        self::assertFalse($buildTools->hasPkgConfigModule('libavif >= 1.0'));

        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('pkg-config')->andReturn('');
        $fs->shouldReceive('resolveCommand')->with('pkgconf')->andReturn('');
        $fs->shouldReceive('isFile')->andReturn(false);
        self::assertFalse((new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger()))->hasPkgConfigModule('aom'));
    }

    /**
     * @return list<array{non-empty-string, bool}>
     */
    public static function providePkgConfigFiles()
    {
        return [
            ['libavif', true],
            ['libavif >= 0.8.2', true],
            ['libavif >= 1.3.0', true],
            ['libavif >= 1.10', false],
            ['aom', false],
        ];
    }

    /**
     * @dataProvider providePkgConfigFiles
     *
     * @param non-empty-string $module
     * @param bool $expected
     *
     * @return void
     */
    public function testPkgConfigModuleWithoutPkgConfig($module, $expected)
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('pkg-config')->andReturn('');
        $fs->shouldReceive('resolveCommand')->with('pkgconf')->andReturn('');
        $fs->shouldReceive('isFile')->with('/usr/local/lib/pkgconfig/libavif.pc')->andReturn(true);
        $fs->shouldReceive('isFile')->andReturn(false);
        $fs->shouldReceive('readFile')->with('/usr/local/lib/pkgconfig/libavif.pc')->andReturn("Name: libavif\nVersion: 1.3.0\nLibs: -lavif\n");
        self::assertSame($expected, (new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger()))->hasPkgConfigModule($module));
    }

    /**
     * @return list<array{int, bool}>
     */
    public static function provideLinkResults()
    {
        return [[0, true], [1, false]];
    }

    /**
     * @dataProvider provideLinkResults
     *
     * @param int $rc
     * @param bool $expected
     *
     * @return void
     */
    public function testCanLinkLibrary($rc, $expected)
    {
        $fs = M::mock(Filesystem::class)->shouldIgnoreMissing();
        $fs->shouldReceive('resolveCommand')->with('cc')->andReturn('/usr/bin/cc');
        $fs->shouldReceive('createVolatileDirectory')->andReturn(new VolatileDirectory('/tmp/x', $fs));
        $fs->shouldReceive('writeFile')->once()->with('/tmp/x/test.c', M::on(static function ($content) {
            return is_string($content) && strpos($content, '#include <libyuv.h>') === 0;
        }));
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('cc', ['/tmp/x/test.c', '-lyuv', '-o', '/tmp/x/test'])->withOutput(Command::OUTPUT_DISCARD)))->andReturn([$rc, '']);
        self::assertSame($expected, (new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger()))->canLinkLibrary('yuv', 'libyuv.h'));
    }

    /**
     * @return void
     */
    public function testCanLinkLibraryWithoutCompiler()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('cc')->andReturn('');
        $fs->shouldReceive('resolveCommand')->with('gcc')->andReturn('');
        self::assertFalse((new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger()))->canLinkLibrary('yuv', 'libyuv.h'));
    }

    /**
     * @return list<array{int, string}>
     */
    public static function provideStackProtectorFlagCases()
    {
        return [[0, '-fstack-protector-strong'], [1, '-fstack-protector']];
    }

    /**
     * @dataProvider provideStackProtectorFlagCases
     *
     * @param int $rc
     * @param string $expected
     *
     * @return void
     */
    public function testStackProtectorFlag($rc, $expected)
    {
        $fs = M::mock(Filesystem::class)->shouldIgnoreMissing();
        $fs->shouldReceive('resolveCommand')->with('cc')->andReturn('/usr/bin/cc');
        $fs->shouldReceive('createVolatileDirectory')->once()->andReturn(new VolatileDirectory('/tmp/x', $fs));
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('cc', ['-fstack-protector-strong', '-c', '/tmp/x/test.c', '-o', '/tmp/x/test.o'])->withOutput(Command::OUTPUT_DISCARD)))->andReturn([$rc, '']);
        $buildTools = new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger());
        self::assertSame($expected, $buildTools->getStackProtectorFlag());
        // The result is cached
        self::assertSame($expected, $buildTools->getStackProtectorFlag());
    }

    /**
     * @return void
     */
    public function testStackProtectorFlagWithoutCompiler()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('cc')->andReturn('');
        $fs->shouldReceive('resolveCommand')->with('gcc')->andReturn('');
        self::assertSame('-fstack-protector-strong', (new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger()))->getStackProtectorFlag());
    }

    /**
     * @return void
     */
    public function testProcessorCount()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('nproc')->andReturn('');
        $fs->shouldReceive('resolveCommand')->with('getconf')->andReturn('/usr/bin/getconf');
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('getconf', ['_NPROCESSORS_ONLN'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT)))->andReturn([0, "8\n"]);
        self::assertSame(8, (new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger()))->getProcessorCount());

        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('nproc')->andReturn('');
        $fs->shouldReceive('resolveCommand')->with('getconf')->andReturn('');
        $fs->shouldReceive('isFile')->with('/proc/cpuinfo')->andReturn(true);
        $fs->shouldReceive('readFile')->with('/proc/cpuinfo')->andReturn(implode("\n", [
            0 => 'processor	: 0',
            1 => 'model name	: x',
            2 => '',
            3 => 'processor	: 1',
            4 => 'model name	: x',
        ]));
        self::assertSame(2, (new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger()))->getProcessorCount());

        $fs = self::createFilesystem();
        $fs->shouldReceive('resolveCommand')->with('nproc')->andReturn('');
        $fs->shouldReceive('resolveCommand')->with('getconf')->andReturn('');
        $fs->shouldReceive('isFile')->with('/proc/cpuinfo')->andReturn(false);
        self::assertSame(1, (new BuildTools(self::createPHPInfo(), $fs, new \IPE\Logger()))->getProcessorCount());
    }
}
