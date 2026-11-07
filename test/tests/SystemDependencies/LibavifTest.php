<?php

namespace IPE\Test\SystemDependencies;

use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor;
use IPE\Filesystem\Command;
use IPE\Filesystem\Downloader;
use IPE\Filesystem\VolatileDirectory;
use IPE\Installer\BuildOptions;
use IPE\Installer\BuildTools;
use IPE\Installer\State;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\libaom;
use IPE\SystemDependencies\libavif;
use IPE\SystemDependencies\libyuv;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class LibavifTest extends TestCase
{
    /**
     * @var list<\IPE\Filesystem\Command>
     */
    private $commands = [];

    /**
     * @param class-string<\IPE\OS\Posix> $osClass
     * @param int $osVersion
     * @param int $phpVersion
     * @param list<string> $installedModules the pkg-config modules that exist
     *
     * @return array{\IPE\PHPInfo, \IPE\OS\Posix, \IPE\Installer\BuildTools, \IPE\Filesystem}
     */
    private function createEnvironment($osClass, $osVersion, $phpVersion, array $installedModules)
    {
        $fs = M::mock(Filesystem::class)->shouldIgnoreMissing();
        $fs->shouldReceive('resolveCommand')->with('pkg-config')->andReturn('/usr/bin/pkg-config');
        $fs->shouldReceive('resolveCommand')->with('make')->andReturn('/usr/bin/make');
        $fs->shouldReceive('resolveCommand')->andReturn('');
        $fs->shouldReceive('exists')->andReturn(false);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('run')->andReturnUsing(function (Command $command) use ($installedModules) {
            $arguments = $command->getArguments();
            if ($command->getCommand() === 'pkg-config') {
                return [in_array($arguments[1], $installedModules, true) ? 0 : 1, ''];
            }
            $this->commands[] = $command;

            return [0, ''];
        });
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
        $os = M::mock($osClass);
        self::assertInstanceOf(OS\Posix::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        $os->shouldReceive('getFilesystem')->andReturn($fs);

        return [$php, $os, new BuildTools($php, $fs, new \IPE\Logger()), $fs];
    }

    /**
     * @param class-string<\IPE\OS\Posix> $osClass
     * @param int $osVersion
     * @param int $phpVersion
     * @param list<string> $installedModules
     *
     * @return \IPE\SystemDependencies\libavif
     */
    private function createLibavif($osClass, $osVersion, $phpVersion, array $installedModules = [])
    {
        list($php, $os, $buildTools) = $this->createEnvironment($osClass, $osVersion, $phpVersion, $installedModules);

        return new libavif($php, $os, new Values(), M::mock(Downloader::class), M::mock(ArchiveExtractor::class), $buildTools, new BuildOptions(), new \IPE\Logger());
    }

    /**
     * @return list<array{class-string<\IPE\OS\Posix>, int, int, bool}>
     */
    public static function provideOSCases()
    {
        return [
            [OS\Debian::class, OS\Debian::VERSION_BULLSEYE, 802, false],
            [OS\Debian::class, OS\Debian::VERSION_BOOKWORM, 802, true],
            [OS\Debian::class, OS\Debian::VERSION_BOOKWORM, 806, false],
            [OS\Debian::class, OS\Debian::VERSION_TRIXIE, 806, true],
            [OS\Alpine::class, 314, 802, false],
            [OS\Alpine::class, 315, 802, true],
            [OS\Alpine::class, 318, 806, false],
            [OS\Alpine::class, 319, 806, true],
            [OS\Ubuntu::class, OS\Ubuntu::FOCAL, 802, false],
            [OS\Ubuntu::class, OS\Ubuntu::JAMMY, 802, true],
            [OS\Ubuntu::class, OS\Ubuntu::JAMMY, 806, false],
            [OS\Ubuntu::class, OS\Ubuntu::NOBLE, 802, true],
            [OS\Ubuntu::class, OS\Ubuntu::NOBLE, 806, true],
            [OS\Posix::class, 1, 802, false],
        ];
    }

    /**
     * @dataProvider provideOSCases
     *
     * @param class-string<\IPE\OS\Posix> $osClass
     * @param int $osVersion
     * @param int $phpVersion
     * @param bool $providedByOS
     *
     * @return void
     */
    public function testProvidedByOS($osClass, $osVersion, $phpVersion, $providedByOS)
    {
        list($php, $os) = $this->createEnvironment($osClass, $osVersion, $phpVersion, []);
        self::assertSame($providedByOS, libavif::isAvailableWithoutCompiling($php, $os));
        $libavif = $this->createLibavif($osClass, $osVersion, $phpVersion);
        self::assertSame($providedByOS ? [] : [libyuv::HANDLE, libaom::HANDLE, 'cmake'], $libavif->getRequiredDependencies());
        self::assertSame($providedByOS, in_array('libavif-dev', $libavif->getSystemPackages(M::mock(State::class))->getVolatile(), true));
    }

    /**
     * @return void
     */
    public function testAlreadyCompiled()
    {
        list($php, $os) = $this->createEnvironment(OS\Debian::class, OS\Debian::VERSION_BULLSEYE, 802, ['libavif >= 0.8.2']);
        self::assertTrue(libavif::isAvailableWithoutCompiling($php, $os));
        self::assertSame([], $this->createLibavif(OS\Debian::class, OS\Debian::VERSION_BULLSEYE, 802, ['libavif >= 0.8.2'])->getRequiredDependencies());
        // PHP 8.6 needs a newer version
        list($php, $os) = $this->createEnvironment(OS\Debian::class, OS\Debian::VERSION_BOOKWORM, 806, ['libavif >= 0.8.2']);
        self::assertFalse(libavif::isAvailableWithoutCompiling($php, $os));
    }

    /**
     * @return void
     */
    public function testNoPackagesWhenCompiling()
    {
        self::assertTrue($this->createLibavif(OS\Alpine::class, 314, 802)->getSystemPackages(M::mock(State::class))->isEmpty());
        self::assertTrue($this->createLibavif(OS\Debian::class, OS\Debian::VERSION_BULLSEYE, 802)->getSystemPackages(M::mock(State::class))->isEmpty());
    }

    /**
     * @return list<array{string, string}>
     */
    public static function provideBuildTypes()
    {
        return [
            [BuildOptions::TYPE_OPTIMIZED, '-DCMAKE_BUILD_TYPE=Release'],
            [BuildOptions::TYPE_DEBUG, '-DCMAKE_BUILD_TYPE=Debug'],
        ];
    }

    /**
     * @dataProvider provideBuildTypes
     *
     * @param string $buildType
     * @param string $expectedCMakeType
     *
     * @return void
     */
    public function testCompilation($buildType, $expectedCMakeType)
    {
        list($php, $os, $buildTools, $fs) = $this->createEnvironment(OS\Debian::class, OS\Debian::VERSION_BULLSEYE, 802, []);
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('saveAs')->once()->with('https://codeload.github.com/AOMediaCodec/libavif/tar.gz/refs/tags/v' . libavif::VERSION, M::type('string'));
        $extractor = M::mock(ArchiveExtractor::class);
        $extractor->shouldReceive('extractTo')->once()->andReturn('/src/avif');
        $state = M::mock(State::class);
        $state->shouldReceive('getVolatileDir')->andReturn(new VolatileDirectory('/tmp/ipe', $fs));
        $options = (new BuildOptions())->withParallelJobs(3)->withType($buildType);

        $libavif = new libavif($php, $os, new Values(), $downloader, $extractor, $buildTools, $options, new \IPE\Logger());
        self::assertSame(libavif::VERSION, $libavif->getCompiledVersion());
        self::assertSame('', $libavif->getCompiledSourceURL());
        $libavif->install($state);
        self::assertSame('https://codeload.github.com/AOMediaCodec/libavif/tar.gz/refs/tags/v' . libavif::VERSION, $libavif->getCompiledSourceURL());

        self::assertCount(3, $this->commands);
        self::assertSame('cmake', $this->commands[0]->getCommand());
        self::assertSame('/src/avif/build', $this->commands[0]->getWorkingDirectory());
        self::assertContains($expectedCMakeType, $this->commands[0]->getArguments());
        self::assertSame('/usr/bin/make', $this->commands[1]->getCommand());
        self::assertSame(['-j3', 'install'], $this->commands[1]->getArguments());
        self::assertSame(Command::OUTPUT_SHOW, $this->commands[1]->getOutput());
        self::assertSame('ldconfig', $this->commands[2]->getCommand());
    }

    /**
     * @return void
     */
    public function testLibyuvNeedsTheCPlusPlusRuntimeOnAlpine()
    {
        list($php, $os, $buildTools) = $this->createEnvironment(OS\Alpine::class, 314, 802, []);
        $libyuv = new libyuv($php, $os, new Values(), M::mock(Downloader::class), M::mock(ArchiveExtractor::class), $buildTools, new BuildOptions(), new \IPE\Logger());
        self::assertSame(['libstdc++'], $libyuv->getSystemPackages(M::mock(State::class))->getPersistent());
        self::assertSame(['cmake', 'lib-jpeg'], $libyuv->getRequiredDependencies());
    }

    /**
     * @return void
     */
    public function testNothingToCompile()
    {
        list($php, $os, $buildTools) = $this->createEnvironment(OS\Debian::class, OS\Debian::VERSION_BOOKWORM, 802, []);
        $downloader = M::mock(Downloader::class);
        $downloader->shouldNotReceive('saveAs');
        $libavif = new libavif($php, $os, new Values(), $downloader, M::mock(ArchiveExtractor::class), $buildTools, new BuildOptions(), new \IPE\Logger());
        $libavif->install(M::mock(State::class));
        self::assertSame([], $this->commands);
        self::assertSame('', $libavif->getCompiledSourceURL());
    }
}
