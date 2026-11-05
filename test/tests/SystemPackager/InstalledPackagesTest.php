<?php

namespace IPE\Test\SystemPackager;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\SystemPackager\APK;
use IPE\SystemPackager\APT;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class InstalledPackagesTest extends TestCase
{
    /**
     * @param non-empty-string $command
     * @param list<string> $arguments
     * @param string $output
     *
     * @return \IPE\OS&\Mockery\MockInterface
     */
    private static function createOS($command, array $arguments, $output)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('run')->with(self::isCommand(Command::create($command, $arguments)->withOutput(Command::OUTPUT_CAPTURE_STDOUT)))->andReturn([0, $output]);
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);

        return $os;
    }

    /**
     * @return list<array{class-string<\IPE\SystemPackager>, non-empty-string, bool}>
     */
    public static function providePackageNames()
    {
        return [
            [APT::class, 'libpng-dev', true],
            [APT::class, 'g++', true],
            [APT::class, 'libfoo=1.2.3-4+deb12u1', true],
            [APT::class, '^libpng[0-9]+-[0-9]+$', true],
            [APT::class, '^libpng', false],
            [APT::class, 'libpng;rm', false],
            [APT::class, 'x', false],
            [APK::class, 'libstdc++', true],
            [APK::class, 'libjpeg-turbo-dev', true],
            [APK::class, 'libfoo>=1.2.3-r0', true],
            [APK::class, '.persistent-deps', true],
            [APK::class, '..persistent-deps', false],
            [APK::class, '^libpng[0-9]+$', false],
            [APK::class, 'libpng;rm', false],
        ];
    }

    /**
     * @dataProvider providePackageNames
     *
     * @param class-string<\IPE\SystemPackager> $class
     * @param non-empty-string $name
     * @param bool $valid
     *
     * @return void
     */
    public function testPackageNameValidation($class, $name, $valid)
    {
        $fs = M::mock(Filesystem::class)->shouldIgnoreMissing();
        $fs->shouldReceive('run')->andReturn([0, '']);
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $packager = new $class($os);
        try {
            $packager->removePackages([$name]);
            self::assertTrue($valid);
        } catch (Exception\InvalidArgument $x) {
            self::assertFalse($valid);
        }
    }

    /**
     * @return void
     */
    public function testAPT()
    {
        $os = self::createOS('dpkg', ['--get-selections'], "adduser\t\t\t\t\tinstall\nlibc6:amd64\t\t\t\tinstall\nold-package\t\t\t\tdeinstall\nzlib1g:amd64\t\t\t\tinstall\n");
        self::assertSame(['adduser', 'libc6', 'zlib1g'], (new APT($os))->listInstalledPackages());
    }

    /**
     * @return void
     */
    public function testAPK()
    {
        $os = self::createOS('apk', ['info'], "musl\nbusybox\n\nalpine-baselayout\n");
        self::assertSame(['musl', 'busybox', 'alpine-baselayout'], (new APK($os))->listInstalledPackages());
    }

    /**
     * @return void
     */
    public function testAPKDoesNotListTheICUDataPickedByItself()
    {
        $output = "(1/2) Installing icu-data-en (74.2-r1)\n(2/2) Installing icu-libs (74.2-r1)\nOK: 10 MiB in 30 packages\n";
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('run')->andReturn([0, $output]);
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $packager = new APK($os);
        self::assertSame(['icu-libs'], array_keys($packager->expandPackageList(['icu-libs'])));
        self::assertSame(['icu-data-en', 'icu-libs'], array_keys($packager->expandPackageList(['icu-data-en', 'icu-libs'])));
    }
}
