<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\ion;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class IonTest extends TestCase
{
    /**
     * @param bool $bundled whether the source code already contains ion-c
     *
     * @return \IPE\Filesystem&\Mockery\MockInterface
     */
    private static function createFilesystem($bundled)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('isFile')->with('/tmp/ipe/ext/ion-c/CMakeLists.txt')->andReturn($bundled);

        return $fs;
    }

    /**
     * @return \IPE\Extensions\ion
     */
    private static function createExtension(Filesystem $fs)
    {
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);

        return new ion(M::mock(PHPInfo::class), $os);
    }

    /**
     * @return void
     */
    public function testCloneIonC()
    {
        $fs = self::createFilesystem(false);
        $command = Command::create('git', ['clone', '-q', '-c', 'advice.detachedHead=false', '--depth', '1', '--recurse-submodules', '--branch', 'v' . ion::IONC_VERSION, 'https://github.com/amzn/ion-c.git', 'ion-c'])
            ->withWorkingDirectory('/tmp/ipe/ext')
            ->withOutput(Command::OUTPUT_SHOW)
        ;
        $fs->shouldReceive('run')->once()->with(self::isCommand($command))->andReturn([0, '']);
        $extension = self::createExtension($fs);
        $extension->patchSourceCode(self::createBuildContext());
        self::assertSame(['--with-ion=shared,bundled'], $extension->getConfigureArguments(self::createBuildContext()));
    }

    /**
     * @return void
     */
    public function testKeepBundledIonC()
    {
        $fs = self::createFilesystem(true);
        $fs->shouldNotReceive('run');
        self::createExtension($fs)->patchSourceCode(self::createBuildContext());
    }
}
