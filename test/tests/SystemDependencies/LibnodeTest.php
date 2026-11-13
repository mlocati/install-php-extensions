<?php

namespace IPE\Test\SystemDependencies;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Installer\State;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\libnode;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class LibnodeTest extends TestCase
{
    /**
     * @param int $osVersion
     * @param string|null $moduleFile the contents of /etc/dnf/modules.d/nodejs.module (null if it doesn't exist)
     *
     * @return \IPE\SystemDependencies\libnode
     */
    private static function createDependency($osVersion, $moduleFile)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('isFile')->with('/etc/dnf/modules.d/nodejs.module')->andReturn($moduleFile !== null);
        $fs->shouldReceive('readFile')->with('/etc/dnf/modules.d/nodejs.module')->andReturn((string) $moduleFile);
        $os = M::mock(OS\AlmaLinux::class);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        $os->shouldReceive('getDisplayName')->andReturn("AlmaLinux {$osVersion}");
        $os->shouldReceive('getFilesystem')->andReturn($fs);

        return new libnode(M::mock(PHPInfo::class), $os, new Values());
    }

    /**
     * @return array<array{int, string|null, bool}>
     */
    public static function provideNodejsModuleCases()
    {
        return [
            [8, null, false],
            [8, "[nodejs]\nname=nodejs\nstream=22\nprofiles=\nstate=enabled\n", true],
            [9, "[nodejs]\nname=nodejs\nstream=\nprofiles=\nstate=\n", false],
            [9, "[nodejs]\nname=nodejs\nstream=22\nprofiles=\nstate=enabled\n", true],
            [10, null, true],
        ];
    }

    /**
     * @dataProvider provideNodejsModuleCases
     *
     * @param int $osVersion
     * @param string|null $moduleFile
     * @param bool $provided
     *
     * @return void
     */
    public function testNodejsModule($osVersion, $moduleFile, $provided)
    {
        $dependency = self::createDependency($osVersion, $moduleFile);
        $state = new State(M::mock(PHPInfo::class), M::mock(Filesystem::class));
        if (!$provided) {
            $this->expectException(Exception\UnsupportedOS::class);
            $this->expectExceptionMessage('dnf module enable nodejs:22');
        }
        $packages = $dependency->getSystemPackages($state);
        self::assertSame(['nodejs-libs'], $packages->getPersistent());
        self::assertSame(['nodejs-devel'], $packages->getVolatile());
    }
}
