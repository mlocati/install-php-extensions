<?php

namespace IPE\Test\Installer;

use IPE\Exception;
use IPE\Extension;
use IPE\Extension\Manifest;
use IPE\Extension\Resolved;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Filesystem\VolatileDirectory;
use IPE\Installer\BuildOptions;
use IPE\Installer\BuildTools;
use IPE\Installer\Compiler;
use IPE\Installer\ZendExtensionDetector;
use IPE\Option;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class CompilerTest extends TestCase
{
    const SOURCE_DIR = '/tmp/ipe/apcu-src';

    const EXTENSION_DIR = '/usr/local/lib/php/extensions/no-debug-non-zts-20240924';

    /**
     * @param int $majorMinorVersion
     *
     * @return \IPE\PHPInfo&\Mockery\MockInterface
     */
    private static function createPHPInfo($majorMinorVersion = 804)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/local/bin/php');
        $php->shouldReceive('getVersion')->andReturn('8.4.14');
        $php->shouldReceive('getMajorMinorVersion')->andReturn($majorMinorVersion);
        $php->shouldReceive('getAPIVersion')->andReturn(20240924);
        $php->shouldReceive('getExtensionDir')->andReturn(self::EXTENSION_DIR);
        $php->shouldReceive('getConfigureCommand')->andReturn("'./configure' '--with-libdir=lib64'");

        return $php;
    }

    /**
     * @return array<non-empty-string, string>
     */
    private static function createConfigureEnvironment()
    {
        return ['CFLAGS' => '-g -O2 -fstack-protector-strong ' . Compiler::WARNING_FLAGS . ' ' . Compiler::C_WARNING_FLAGS, 'CXXFLAGS' => rtrim('-g -O2 -fstack-protector-strong ' . Compiler::WARNING_FLAGS . ' ' . Compiler::CXX_WARNING_FLAGS)];
    }

    /**
     * @return array<non-empty-string, string>
     */
    private static function createMakeEnvironment()
    {
        return ['EXTRA_CFLAGS' => '-Werror=implicit-function-declaration'];
    }

    /**
     * A filesystem where phpize, php-config, make and nproc are available.
     *
     * @return \IPE\Filesystem&\Mockery\MockInterface
     */
    private static function createFilesystem()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->with('/usr/local/bin/phpize')->andReturn(true);
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('/usr/local/bin/phpize', ['--version'])))->andReturn([0, "PHP Api Version: 20240924\n"]);
        $fs->shouldReceive('isFile')->with('/usr/local/bin/php-config')->andReturn(true);
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('/usr/local/bin/php-config', ['--version'])))->andReturn([0, "8.4.14\n"]);
        $fs->shouldReceive('resolveCommand')->with('make')->andReturn('/usr/bin/make');
        $fs->shouldReceive('resolveCommand')->with('nproc')->andReturn('/usr/bin/nproc');
        $fs->shouldReceive('resolveCommand')->with('readelf')->andReturn('');
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('nproc', [])->withOutput(Command::OUTPUT_CAPTURE_STDOUT)))->andReturn([0, "4\n"]);
        $fs->shouldReceive('resolveCommand')->with('cc')->andReturn('/usr/bin/cc');
        $fs->shouldReceive('createVolatileDirectory')->andReturnUsing(static function () use ($fs) {
            return new VolatileDirectory('/tmp/cc', $fs);
        });
        $fs->shouldReceive('writeFile')->with('/tmp/cc/test.c', M::type('string'));
        $fs->shouldReceive('deleteDirectory')->with('/tmp/cc');
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('cc', ['-fstack-protector-strong', '-c', '/tmp/cc/test.c', '-o', '/tmp/cc/test.o'])->withOutput(Command::OUTPUT_DISCARD)))->andReturn([0, '']);

        return $fs;
    }

    /**
     * @return void
     */
    public function testCompileUnknownExtension()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn(true);
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/configure')->andReturn(false);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('./configure', ['--with-php-config=/usr/local/bin/php-config', '--with-libdir=lib64', '--enable-apcu-debug'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createConfigureEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/bin/make', ['-j4'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createMakeEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('isDirectory')->with(self::SOURCE_DIR . '/modules')->andReturn(true);
        $fs->shouldReceive('listDirectoryContents')->with(self::SOURCE_DIR . '/modules')->andReturn(['apcu.la', 'apcu.so']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/bin/make', ['-j4', 'install'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createMakeEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('isFile')->with(self::EXTENSION_DIR . '/apcu.so')->andReturn(true);
        $fs->shouldReceive('readFile')->with(self::EXTENSION_DIR . '/apcu.so')->andReturn("\x7fELF...get_module...");
        $fs->shouldReceive('resolveCommand')->with('strip')->andReturn('/usr/bin/strip');
        $fs->shouldReceive('getFileSize')->with(self::EXTENSION_DIR . '/apcu.so')->andReturn(300000, 100000);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/bin/strip', ['--strip-all', self::EXTENSION_DIR . '/apcu.so'])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $php = self::createPHPInfo();
        $resolved = new Resolved(null, self::SOURCE_DIR, '5.1.24', Resolved::ORIGIN_DIRECTORY, null, new Option\Values(['--enable-apcu-debug']));

        $compiled = (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), new BuildOptions(), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile($resolved);

        self::assertSame($resolved, $compiled->getResolved());
        self::assertSame('apcu', $compiled->getModuleName());
        self::assertSame(self::EXTENSION_DIR . '/apcu.so', $compiled->getModulePath());
        self::assertFalse($compiled->isZendExtension());
    }

    /**
     * @return void
     */
    public function testCompileWithManifestAndPreviousBuild()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn(false);
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config0.m4')->andReturn(true);
        $fs->shouldReceive('rename')->once()->with(self::SOURCE_DIR . '/config0.m4', self::SOURCE_DIR . '/config.m4');
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/configure')->andReturn(true);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/local/bin/phpize', ['--clean'])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        // The user options win over the detected php-config and libdir
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('./configure', ['--with-php-config=/opt/php/bin/php-config', '--with-libdir=lib'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createConfigureEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/bin/make', ['-j2'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createMakeEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('isDirectory')->with(self::SOURCE_DIR . '/modules')->andReturn(true);
        $fs->shouldReceive('listDirectoryContents')->with(self::SOURCE_DIR . '/modules')->andReturn(['helper.so', 'opcache.so']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/bin/make', ['-j2', 'install'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createMakeEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('isFile')->with(self::EXTENSION_DIR . '/opcache.so')->andReturn(true);
        $php = self::createPHPInfo();
        $manifest = new Manifest(Manifest::TYPE_COMPOSER, 'php/opcache', 'opcache');
        $manifest->setZendExtension(true);
        $resolved = new Resolved(null, self::SOURCE_DIR, '8.4.14', Resolved::ORIGIN_DIRECTORY, $manifest, new Option\Values(['--with-php-config=/opt/php/bin/php-config', '--with-libdir=lib']));

        $compiled = (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), (new BuildOptions())->withParallelJobs(2)->withType(BuildOptions::TYPE_WITH_SYMBOLS), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile($resolved);

        self::assertSame('opcache', $compiled->getModuleName());
        self::assertTrue($compiled->isZendExtension());
    }

    /**
     * @return void
     */
    public function testConfigureArgumentsProvidedByTheExtension()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn(true);
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/configure')->andReturn(false);
        $php = self::createPHPInfo();
        $extension = M::mock(Extension::class . ', ' . Extension\ProvidesConfigureArguments::class);
        $extension->shouldReceive('getConfigureArguments')->once()->with(self::isBuildContext(self::SOURCE_DIR, '1.0.0'))->andReturnUsing(static function (Extension\BuildContext $context) {
            return ['--enable-foo=' . $context->getOptionValues()->getCustomValue('variant'), '--with-baz', '--with-libdir=lib'];
        });
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('./configure', ['--with-qux=no', '--with-php-config=/usr/local/bin/php-config', '--enable-foo=alt', '--with-libdir=lib', '--without-baz'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createConfigureEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([1, '']);
        $resolved = new Resolved($extension, self::SOURCE_DIR, '1.0.0', Resolved::ORIGIN_BUNDLED, null, new Option\Values(['--without-baz'], ['variant' => 'alt'], ['--enable-foo=no', '--with-qux=no']));
        $this->expectException(Exception\IO::class);
        (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), new BuildOptions(), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile($resolved);
    }

    /**
     * @return void
     */
    public function testExtensionHooks()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn(true);
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/configure')->andReturn(false);
        $php = self::createPHPInfo();
        $extension = M::mock(Extension::class . ', ' . Extension\PatchesSourceCode::class . ', ' . Extension\PatchesConfigureScript::class);
        $extension->shouldReceive('getHandle')->andReturn('foo');
        $extension->shouldReceive('getInstalledNames')->andReturn(['foo']);
        $extension->shouldReceive('patchSourceCode')->once()->ordered()->with(self::isBuildContext(self::SOURCE_DIR, '1.0.0'));
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $extension->shouldReceive('patchConfigureScript')->once()->ordered()->with(self::isBuildContext(self::SOURCE_DIR, '1.0.0'));
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('./configure', ['--with-php-config=/usr/local/bin/php-config', '--with-libdir=lib64'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createConfigureEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([1, '']);
        $resolved = new Resolved($extension, self::SOURCE_DIR, '1.0.0', Resolved::ORIGIN_BUNDLED);
        try {
            (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), new BuildOptions(), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile($resolved);
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('configure failed', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testDebugBuild()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn(true);
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/configure')->andReturn(false);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('./configure', ['--with-php-config=/usr/local/bin/php-config', '--with-libdir=lib64'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(['CFLAGS' => '-O0 -g -fstack-protector-strong ' . Compiler::WARNING_FLAGS . ' ' . Compiler::C_WARNING_FLAGS, 'CXXFLAGS' => rtrim('-O0 -g -fstack-protector-strong ' . Compiler::WARNING_FLAGS . ' ' . Compiler::CXX_WARNING_FLAGS)])->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/bin/make', ['-j4'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createMakeEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('isDirectory')->with(self::SOURCE_DIR . '/modules')->andReturn(true);
        $fs->shouldReceive('listDirectoryContents')->with(self::SOURCE_DIR . '/modules')->andReturn(['lzf.so']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/bin/make', ['-j4', 'install'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createMakeEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('isFile')->with(self::EXTENSION_DIR . '/lzf.so')->andReturn(true);
        $fs->shouldReceive('readFile')->with(self::EXTENSION_DIR . '/lzf.so')->andReturn('ELF');
        $fs->shouldNotReceive('resolveCommand')->with('strip');
        $php = self::createPHPInfo();
        $resolved = new Resolved(null, self::SOURCE_DIR, '', Resolved::ORIGIN_DIRECTORY);

        $compiled = (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), (new BuildOptions())->withType(BuildOptions::TYPE_DEBUG), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile($resolved);

        self::assertSame('lzf', $compiled->getModuleName());
    }

    /**
     * @return void
     */
    public function testSedOnOldPHP()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn(true);
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/configure')->andReturn(false);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('./configure', ['--with-php-config=/usr/local/bin/php-config', '--with-libdir=lib64'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createConfigureEnvironment() + ['SED' => 'sed', 'PKG_CONFIG' => 'pkg-config'])->withOutput(Command::OUTPUT_SHOW)))->andReturn([1, '']);
        $php = self::createPHPInfo(703);
        try {
            (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), new BuildOptions(), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile(new Resolved(null, self::SOURCE_DIR, '', Resolved::ORIGIN_DIRECTORY));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertSame('configure failed with exit code 1', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testPDOIncludesOnPHP5()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn(true);
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/configure')->andReturn(false);
        $fs->shouldReceive('run')->with(self::isCommand(Command::create('/usr/local/bin/php-config', ['--include-dir'])))->andReturn([0, "/usr/include/php/20131226\n"]);
        $fs->shouldReceive('isFile')->with('/usr/include/php/20131226/ext/pdo/php_pdo_driver.h')->andReturn(true);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('./configure', ['--with-php-config=/usr/local/bin/php-config', '--with-libdir=lib64'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createConfigureEnvironment() + ['SED' => 'sed', 'PKG_CONFIG' => 'pkg-config', 'pdo_cv_inc_path' => '/usr/include/php/20131226/ext'])->withOutput(Command::OUTPUT_SHOW)))->andReturn([1, '']);
        $php = self::createPHPInfo(506);
        try {
            (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), new BuildOptions(), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile(new Resolved(null, self::SOURCE_DIR, '', Resolved::ORIGIN_DIRECTORY));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertSame('configure failed with exit code 1', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testFailingStep()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn(true);
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/configure')->andReturn(false);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('./configure', ['--with-php-config=/usr/local/bin/php-config', '--with-libdir=lib64'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createConfigureEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([1, '']);
        $php = self::createPHPInfo();
        $resolved = new Resolved(null, self::SOURCE_DIR, '', Resolved::ORIGIN_DIRECTORY);
        try {
            (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), new BuildOptions(), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile($resolved);
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertSame('configure failed with exit code 1', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testUserFlagsComeLast()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn(true);
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/configure')->andReturn(false);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('./configure', ['--with-php-config=/usr/local/bin/php-config', '--with-libdir=lib64'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(['CFLAGS' => '-g -O2 -fstack-protector-strong ' . Compiler::WARNING_FLAGS . ' ' . Compiler::C_WARNING_FLAGS . ' -DFOO', 'CXXFLAGS' => rtrim('-g -O2 -fstack-protector-strong ' . Compiler::WARNING_FLAGS . ' ' . Compiler::CXX_WARNING_FLAGS)])->withOutput(Command::OUTPUT_SHOW)))->andReturn([1, '']);
        $php = self::createPHPInfo();
        putenv('CFLAGS=-DFOO');
        try {
            (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), new BuildOptions(), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile(new Resolved(null, self::SOURCE_DIR, '', Resolved::ORIGIN_DIRECTORY));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertSame('configure failed with exit code 1', $x->getMessage());
        } finally {
            putenv('CFLAGS');
        }
    }

    /**
     * @return array<array{string|false, string}> the CPPFLAGS of the user, and the expected CPPFLAGS
     */
    public static function provideConfigureEnvironment()
    {
        return [
            [false, '-I/usr/src/php'],
            ['', '-I/usr/src/php'],
            ['-DFOO', '-I/usr/src/php -DFOO'],
        ];
    }

    /**
     * @dataProvider provideConfigureEnvironment
     *
     * @param string|false $userFlags
     * @param string $expected
     *
     * @return void
     */
    public function testConfigureEnvironmentProvidedByTheExtension($userFlags, $expected)
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn(true);
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/configure')->andReturn(false);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('./configure', ['--with-php-config=/usr/local/bin/php-config', '--with-libdir=lib64'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(['CFLAGS' => '-g -O2 -fstack-protector-strong ' . Compiler::WARNING_FLAGS . ' ' . Compiler::C_WARNING_FLAGS . ' -Wno-foo', 'CXXFLAGS' => rtrim('-g -O2 -fstack-protector-strong ' . Compiler::WARNING_FLAGS . ' ' . Compiler::CXX_WARNING_FLAGS), 'CPPFLAGS' => $expected])->withOutput(Command::OUTPUT_SHOW)))->andReturn([1, '']);
        $php = self::createPHPInfo();
        $extension = M::mock(Extension::class . ', ' . Extension\ProvidesConfigureEnvironment::class);
        $extension->shouldReceive('getConfigureEnvironment')->once()->with(self::isBuildContext(self::SOURCE_DIR, '1.0.0'))->andReturn(['CFLAGS' => '-Wno-foo', 'CPPFLAGS' => '-I/usr/src/php']);
        putenv($userFlags === false ? 'CPPFLAGS' : "CPPFLAGS={$userFlags}");
        try {
            (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), new BuildOptions(), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile(new Resolved($extension, self::SOURCE_DIR, '1.0.0', Resolved::ORIGIN_BUNDLED));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertSame('configure failed with exit code 1', $x->getMessage());
        } finally {
            putenv('CPPFLAGS');
        }
    }

    /**
     * @return void
     */
    public function testEmptyConfigureEnvironment()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn(true);
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/configure')->andReturn(false);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('./configure', ['--with-php-config=/usr/local/bin/php-config', '--with-libdir=lib64'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createConfigureEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([1, '']);
        $php = self::createPHPInfo();
        $extension = M::mock(Extension::class . ', ' . Extension\ProvidesConfigureEnvironment::class);
        $extension->shouldReceive('getConfigureEnvironment')->once()->andReturn(['CPPFLAGS' => '']);
        $this->expectExceptionMessage('configure failed with exit code 1');
        (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), new BuildOptions(), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile(new Resolved($extension, self::SOURCE_DIR, '1.0.0', Resolved::ORIGIN_BUNDLED));
    }

    /**
     * @return void
     */
    public function testUserExtraFlagsComeLast()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn(true);
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/configure')->andReturn(false);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('./configure', ['--with-php-config=/usr/local/bin/php-config', '--with-libdir=lib64'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createConfigureEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('/usr/bin/make', ['-j4'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(['EXTRA_CFLAGS' => '-Werror=implicit-function-declaration -Wno-error=implicit-function-declaration'])->withOutput(Command::OUTPUT_SHOW)))->andReturn([2, '']);
        $php = self::createPHPInfo();
        putenv('EXTRA_CFLAGS=-Wno-error=implicit-function-declaration');
        try {
            (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), new BuildOptions(), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile(new Resolved(null, self::SOURCE_DIR, '', Resolved::ORIGIN_DIRECTORY));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertSame('make failed with exit code 2', $x->getMessage());
        } finally {
            putenv('EXTRA_CFLAGS');
        }
    }

    /**
     * @return void
     */
    public function testMakeEnvironment()
    {
        $fs = self::createFilesystem();
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn(true);
        $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/configure')->andReturn(false);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory(self::SOURCE_DIR)->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('./configure', ['--with-php-config=/usr/local/bin/php-config', '--with-libdir=lib64'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(self::createConfigureEnvironment())->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('/usr/bin/make', ['-j4'])->withWorkingDirectory(self::SOURCE_DIR)->withEnvironment(['EXTRA_CFLAGS' => '-Werror=implicit-function-declaration -Wno-error', 'EXTRA_CXXFLAGS' => '-include cstdint -DUSER', 'EXTRA_LDFLAGS' => '-lresolv'])->withOutput(Command::OUTPUT_SHOW)))->andReturn([2, '']);
        $php = self::createPHPInfo();
        $extension = M::mock(Extension::class . ', ' . Extension\ProvidesMakeEnvironment::class);
        $extension->shouldReceive('getMakeEnvironment')->andReturn(['EXTRA_CFLAGS' => '-Wno-error', 'EXTRA_CXXFLAGS' => '-include cstdint', 'EXTRA_LDFLAGS' => '-lresolv']);
        putenv('EXTRA_CXXFLAGS=-DUSER');
        try {
            (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), new BuildOptions(), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile(new Resolved($extension, self::SOURCE_DIR, '1.0.0', Resolved::ORIGIN_BUNDLED));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertSame('make failed with exit code 2', $x->getMessage());
        } finally {
            putenv('EXTRA_CXXFLAGS');
        }
    }

    /**
     * @return void
     */
    public function testNotAnExtension()
    {
        $fs = self::createFilesystem();
        foreach (['config.m4', 'config0.m4', 'config9.m4'] as $file) {
            $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/' . $file)->andReturn(false);
        }
        $fs->shouldReceive('listDirectoryContents')->with(self::SOURCE_DIR)->andReturn(['README.md']);
        $fs->shouldReceive('isDirectory')->with(self::SOURCE_DIR . '/README.md')->andReturn(false);
        $php = self::createPHPInfo();
        $resolved = new Resolved(null, self::SOURCE_DIR, '', Resolved::ORIGIN_DIRECTORY);
        try {
            (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), new BuildOptions(), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile($resolved);
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('config.m4', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testSourceInSubdirectory()
    {
        $subdirectory = self::SOURCE_DIR . '/ext';
        $fs = self::createFilesystem();
        foreach (['config.m4', 'config0.m4', 'config9.m4'] as $file) {
            $fs->shouldReceive('isFile')->with(self::SOURCE_DIR . '/' . $file)->andReturn(false);
        }
        $fs->shouldReceive('listDirectoryContents')->with(self::SOURCE_DIR)->andReturn(['README.md', 'ext']);
        $fs->shouldReceive('isDirectory')->with(self::SOURCE_DIR . '/README.md')->andReturn(false);
        $fs->shouldReceive('isDirectory')->with($subdirectory)->andReturn(true);
        $fs->shouldReceive('isFile')->with($subdirectory . '/config.m4')->andReturn(true);
        $fs->shouldReceive('isFile')->with($subdirectory . '/configure')->andReturn(false);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('/usr/local/bin/phpize', [])->withWorkingDirectory($subdirectory)->withOutput(Command::OUTPUT_SHOW)))->andThrow(new Exception\IO('stop here'));
        $php = self::createPHPInfo();
        $extension = M::mock(Extension::class . ', ' . Extension\PatchesSourceCode::class);
        $extension->shouldReceive('getHandle')->andReturn('foo');
        $extension->shouldReceive('getInstalledNames')->andReturn(['foo']);
        $extension->shouldReceive('patchSourceCode')->once()->with(self::isBuildContext($subdirectory, '1.0.0'));
        $resolved = new Resolved($extension, self::SOURCE_DIR, '1.0.0', Resolved::ORIGIN_DIRECTORY);
        $this->expectExceptionMessage('stop here');
        (new Compiler(new BuildTools($php, $fs, new \IPE\Logger()), new BuildOptions(), $php, $fs, new ZendExtensionDetector($fs, new \IPE\Logger()), new \IPE\Logger()))->compile($resolved);
    }

    /**
     * @param non-empty-string $sourceDirectory
     * @param string $version
     *
     * @return \Mockery\Matcher\Closure
     */
    private static function isBuildContext($sourceDirectory, $version)
    {
        return M::on(static function ($actual) use ($sourceDirectory, $version) {
            return $actual instanceof Extension\BuildContext && $actual->getSourceDirectory() === $sourceDirectory && $actual->getVersion() === $version;
        });
    }
}
