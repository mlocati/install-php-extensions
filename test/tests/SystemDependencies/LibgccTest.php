<?php

namespace IPE\Test\SystemDependencies;

use IPE\Installer\State;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\libgcc;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class LibgccTest extends TestCase
{
    /**
     * @return array<array{class-string<\IPE\OS\Posix>, int, list<string>}>
     */
    public static function provideSystemPackagesCases()
    {
        return [
            [OS\Alpine::class, 324, ['libgcc']],
            [OS\Debian::class, OS\Debian::BUSTER, ['libgcc1']],
            [OS\Debian::class, OS\Debian::BOOKWORM, ['libgcc-s1']],
            [OS\Ubuntu::class, OS\Ubuntu::BIONIC, ['libgcc1']],
            [OS\Ubuntu::class, OS\Ubuntu::FOCAL, ['libgcc-s1']],
            [OS\AlmaLinux::class, 10, ['libgcc']],
        ];
    }

    /**
     * @dataProvider provideSystemPackagesCases
     *
     * @param class-string<\IPE\OS\Posix> $osClass
     * @param int $osVersion
     * @param list<string> $persistent
     *
     * @return void
     */
    public function testSystemPackages($osClass, $osVersion, array $persistent)
    {
        $os = M::mock($osClass);
        self::assertInstanceOf(OS\Posix::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        $packages = (new libgcc(M::mock(PHPInfo::class), $os, new Values()))->getSystemPackages(M::mock(State::class));
        self::assertSame($persistent, $packages->getPersistent());
        self::assertSame([], $packages->getVolatile());
    }
}
