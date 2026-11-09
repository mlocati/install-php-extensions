<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\cassandra;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Installer\BuildOptions;
use IPE\Installer\BuildTools;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class CassandraTest extends TestCase
{
    /**
     * @return array<array{string, string}> the build type, and the expected CMake build type
     */
    public static function provideCMakeBuildType()
    {
        return [
            [BuildOptions::TYPE_OPTIMIZED, 'Release'],
            [BuildOptions::TYPE_WITH_SYMBOLS, 'RelWithDebInfo'],
            [BuildOptions::TYPE_DEBUG, 'Debug'],
        ];
    }

    /**
     * @dataProvider provideCMakeBuildType
     *
     * @param string $buildType
     * @param string $expected
     *
     * @return void
     */
    public function testCMakeBuildType($buildType, $expected)
    {
        $commands = [];
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->with('/usr/local/bin/php-config')->andReturn(true);
        $fs->shouldReceive('run')->andReturnUsing(static function (Command $command) use (&$commands) {
            if ($command->getCommand() === '/usr/local/bin/php-config') {
                return [0, "8.3.20\n"];
            }
            $commands[] = $command;

            return [0, ''];
        });
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/local/bin/php');
        $php->shouldReceive('getVersion')->andReturn('8.3.20');
        $php->shouldReceive('getMajorMinorVersion')->andReturn(803);
        $php->shouldReceive('getBits')->andReturn(64);
        $php->shouldReceive('getExtensionDir')->andReturn('/ext');
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getNumericVersion')->andReturn(OS\Debian::TRIXIE);
        $os->shouldReceive('getMachineArchitecture')->andReturn(OS\Architecture::ARM64);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $buildOptions = (new BuildOptions())->withType($buildType)->withParallelJobs(2);
        $extension = new cassandra($php, $os);
        self::assertSame('/ext/cassandra.so', $extension->buildModule(self::createBuildContext('', null, '/src', $buildOptions), new BuildTools($php, $fs, new \IPE\Logger())));
        self::assertSame('cmake', $commands[0]->getCommand());
        self::assertContains("-DCMAKE_BUILD_TYPE={$expected}", $commands[0]->getArguments());
        self::assertSame(['--build', 'cmake-build', '--parallel', '2'], $commands[2]->getArguments());
    }
}
