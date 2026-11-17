<?php

namespace IPE\Test\SystemDependencies;

use IPE\Exception;
use IPE\Installer\State;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class BuildToolsTest extends TestCase
{
    /**
     * @return array<array{class-string<\IPE\SystemDependency>, class-string<\IPE\OS\Posix>, string}>
     */
    public static function provideSystemPackagesCases()
    {
        return [
            [SystemDependencies\binutils::class, OS\Alpine::class, 'binutils'],
            [SystemDependencies\binutils::class, OS\Debian::class, 'binutils'],
            [SystemDependencies\binutils::class, OS\Ubuntu::class, 'binutils'],
            [SystemDependencies\binutils::class, OS\AlmaLinux::class, 'binutils'],
            [SystemDependencies\binutils::class, OS\Fedora::class, 'binutils'],
            [SystemDependencies\binutils::class, OS\AmazonLinux::class, 'binutils'],
            [SystemDependencies\cmake::class, OS\Alpine::class, 'cmake'],
            [SystemDependencies\cmake::class, OS\Debian::class, 'cmake'],
            [SystemDependencies\cmake::class, OS\Ubuntu::class, 'cmake'],
            [SystemDependencies\cmake::class, OS\AlmaLinux::class, 'cmake'],
            [SystemDependencies\cmake::class, OS\Fedora::class, 'cmake'],
            [SystemDependencies\cmake::class, OS\AmazonLinux::class, 'cmake'],
            [SystemDependencies\nasm::class, OS\Alpine::class, 'nasm'],
            [SystemDependencies\nasm::class, OS\Debian::class, 'nasm'],
            [SystemDependencies\nasm::class, OS\Ubuntu::class, 'nasm'],
            [SystemDependencies\nasm::class, OS\AlmaLinux::class, 'nasm'],
            [SystemDependencies\nasm::class, OS\Fedora::class, 'nasm'],
            [SystemDependencies\nasm::class, OS\AmazonLinux::class, 'nasm'],
            [SystemDependencies\linuxheaders::class, OS\Alpine::class, 'linux-headers'],
            [SystemDependencies\linuxheaders::class, OS\Debian::class, 'linux-libc-dev'],
            [SystemDependencies\linuxheaders::class, OS\Ubuntu::class, 'linux-libc-dev'],
            [SystemDependencies\linuxheaders::class, OS\AlmaLinux::class, 'kernel-headers'],
            [SystemDependencies\linuxheaders::class, OS\Fedora::class, 'kernel-headers'],
            [SystemDependencies\linuxheaders::class, OS\AmazonLinux::class, 'kernel-headers'],
        ];
    }

    /**
     * @dataProvider provideSystemPackagesCases
     *
     * @param class-string<\IPE\SystemDependency> $dependencyClass
     * @param class-string<\IPE\OS\Posix> $osClass
     * @param string $package
     *
     * @return void
     */
    public function testSystemPackages($dependencyClass, $osClass, $package)
    {
        $os = M::mock($osClass);
        self::assertInstanceOf(OS\Posix::class, $os);
        $packages = (new $dependencyClass(M::mock(PHPInfo::class), $os, new Values()))->getSystemPackages(M::mock(State::class));
        self::assertSame([], $packages->getPersistent());
        self::assertSame([$package], $packages->getVolatile());
    }

    /**
     * @return array<array{class-string<\IPE\SystemDependency>}>
     */
    public static function provideUnknownOSCases()
    {
        return [
            [SystemDependencies\binutils::class],
            [SystemDependencies\cmake::class],
            [SystemDependencies\nasm::class],
            [SystemDependencies\linuxheaders::class],
        ];
    }

    /**
     * @dataProvider provideUnknownOSCases
     *
     * @param class-string<\IPE\SystemDependency> $dependencyClass
     *
     * @return void
     */
    public function testUnknownOS($dependencyClass)
    {
        $os = M::mock(OS\Posix::class);
        $os->shouldReceive('getDisplayName')->andReturn('Test OS');
        $this->expectException(Exception\UnsupportedOS::class);
        $this->expectExceptionMessage('are not known for Test OS');
        (new $dependencyClass(M::mock(PHPInfo::class), $os, new Values()))->getSystemPackages(M::mock(State::class));
    }
}
