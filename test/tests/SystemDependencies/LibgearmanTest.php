<?php

namespace IPE\Test\SystemDependencies;

use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor;
use IPE\Filesystem\Downloader;
use IPE\Installer\BuildOptions;
use IPE\Installer\State;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\libgearman;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class LibgearmanTest extends TestCase
{
    /**
     * @return list<array{class-string<\IPE\OS\Posix>, int, list<string>, list<string>}>
     */
    public static function provideRHELLikeCases()
    {
        $epel = [
            ['libgearman'],
            ['libgearman-devel'],
        ];
        $compiled = [
            ['libstdc++', 'libuuid'],
            ['boost-devel', 'gperf', 'libevent-devel', 'libuuid-devel'],
        ];

        return [
            [OS\AlmaLinux::class, 8, $epel[0], $epel[1]],
            [OS\RockyLinux::class, 9, $epel[0], $epel[1]],
            [OS\AlmaLinux::class, 10, $compiled[0], $compiled[1]],
            [OS\Fedora::class, 44, $compiled[0], $compiled[1]],
            [OS\AmazonLinux::class, 2023, $compiled[0], $compiled[1]],
        ];
    }

    /**
     * @dataProvider provideRHELLikeCases
     *
     * @param class-string<\IPE\OS\Posix> $osClass
     * @param int $osVersion
     * @param list<string> $persistent
     * @param list<string> $volatile
     *
     * @return void
     */
    public function testRHELLike($osClass, $osVersion, array $persistent, array $volatile)
    {
        $fs = M::mock(Filesystem::class)->shouldIgnoreMissing();
        $php = M::mock(PHPInfo::class);
        $os = M::mock($osClass);
        self::assertInstanceOf(OS\Posix::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $libgearman = new libgearman($php, $os, new Values(), M::mock(Downloader::class), M::mock(ArchiveExtractor::class), self::createBuildTools($php, $fs), new BuildOptions(), new \IPE\Logger());
        $packages = $libgearman->getSystemPackages(M::mock(State::class));
        self::assertSame($persistent, $packages->getPersistent());
        self::assertSame($volatile, $packages->getVolatile());
    }
}
