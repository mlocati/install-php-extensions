<?php

namespace IPE\Test\Extensions;

use IPE\Extensions;
use IPE\Filesystem;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\libbrotli;
use IPE\SystemDependencies\libcurl;
use IPE\SystemDependencies\libevent;
use IPE\SystemDependencies\libgcrypt;
use IPE\SystemDependencies\libicu;
use IPE\SystemDependencies\libidn;
use IPE\SystemDependencies\libidn2;
use IPE\SystemDependencies\libidnkit;
use IPE\SystemDependencies\libopenssl;
use IPE\SystemDependencies\libzlib;
use IPE\SystemPackager\Homebrew;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class HttpTest extends TestCase
{
    /**
     * @param int $version
     *
     * @return \IPE\PHPInfo
     */
    private static function createPHP($version)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($version);

        return $php;
    }

    /**
     * @param class-string<\IPE\OS\Posix> $class
     * @param int $version
     *
     * @return \IPE\OS\Posix
     */
    private static function createOS($class, $version)
    {
        $os = M::mock($class);
        self::assertInstanceOf(OS\Posix::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($version);

        return $os;
    }

    /**
     * @return void
     */
    public function testExtension()
    {
        $os = M::mock(OS::class);
        self::assertSame('http', Extensions\http::getHandle());
        self::assertSame('pecl_http', Extensions\http::getPECLHandle());
        self::assertSame('2.6.0', (new Extensions\http(self::createPHP(506), $os))->getDefaultVersionRequirements());
        self::assertSame('3.2.4', (new Extensions\http(self::createPHP(700), $os))->getDefaultVersionRequirements());
        self::assertSame('3.2.4', (new Extensions\http(self::createPHP(704), $os))->getDefaultVersionRequirements());
        self::assertSame('', (new Extensions\http(self::createPHP(800), $os))->getDefaultVersionRequirements());
        self::assertSame(['propro', 'raphf'], (new Extensions\http(self::createPHP(704), $os))->getRequiredExtensions(''));
        self::assertSame(['raphf'], (new Extensions\http(self::createPHP(800), $os))->getRequiredExtensions(''));
        self::assertSame([], (new Extensions\http(self::createPHP(800), $os))->getOptionalExtensions(''));
    }

    /**
     * @return void
     */
    public function testDependencyVersions()
    {
        $os = M::mock(OS::class);
        self::assertSame('1.1.2', (new Extensions\raphf(self::createPHP(506), $os))->getDefaultVersionRequirements());
        self::assertSame('', (new Extensions\raphf(self::createPHP(700), $os))->getDefaultVersionRequirements());
        self::assertSame('1.0.2', (new Extensions\propro(self::createPHP(506), $os))->getDefaultVersionRequirements());
        self::assertSame('', (new Extensions\propro(self::createPHP(700), $os))->getDefaultVersionRequirements());
    }

    /**
     * @return void
     */
    public function testSystemPackages()
    {
        $alpine = new Extensions\http(self::createPHP(804), self::createOS(OS\Alpine::class, 323));
        self::assertSame(
            [libcurl::HANDLE, libevent::HANDLE, libidn::HANDLE, libzlib::HANDLE],
            $alpine->getRequiredSystemDependencies('2.6.0', new Values())
        );
        self::assertSame(
            [libbrotli::HANDLE, libcurl::HANDLE, libevent::HANDLE, libicu::HANDLE, libidn::HANDLE, libidnkit::HANDLE, libzlib::HANDLE],
            $alpine->getRequiredSystemDependencies('', new Values())
        );
        $debian = new Extensions\http(self::createPHP(804), self::createOS(OS\Debian::class, OS\Debian::TRIXIE));
        self::assertSame(
            [libcurl::HANDLE, libevent::HANDLE, libidn::HANDLE, libopenssl::HANDLE, libzlib::HANDLE],
            $debian->getRequiredSystemDependencies('2.6.0', new Values())
        );
        self::assertSame(
            [libcurl::HANDLE, libevent::HANDLE, libgcrypt::HANDLE, libidn2::HANDLE, libopenssl::HANDLE, libzlib::HANDLE],
            $debian->getRequiredSystemDependencies('3.0.0', new Values())
        );
        self::assertSame(
            [libcurl::HANDLE, libevent::HANDLE, libgcrypt::HANDLE, libicu::HANDLE, libidn2::HANDLE, libidnkit::HANDLE, libopenssl::HANDLE, libzlib::HANDLE],
            $debian->getRequiredSystemDependencies('3.1.0', new Values())
        );
        self::assertSame(
            [libbrotli::HANDLE, libcurl::HANDLE, libevent::HANDLE, libgcrypt::HANDLE, libicu::HANDLE, libidn2::HANDLE, libidnkit::HANDLE, libopenssl::HANDLE, libzlib::HANDLE],
            $debian->getRequiredSystemDependencies('3.2.4', new Values())
        );
        self::assertSame(
            [libbrotli::HANDLE, libcurl::HANDLE, libevent::HANDLE, libgcrypt::HANDLE, libicu::HANDLE, libidn2::HANDLE, libidnkit::HANDLE, libopenssl::HANDLE, libzlib::HANDLE],
            $debian->getRequiredSystemDependencies('', new Values())
        );
        self::assertSame(
            [libcurl::HANDLE, libevent::HANDLE, libgcrypt::HANDLE, libicu::HANDLE, libidn2::HANDLE, libidnkit::HANDLE, libopenssl::HANDLE, libzlib::HANDLE],
            (new Extensions\http(self::createPHP(703), self::createOS(OS\Debian::class, OS\Debian::STRETCH)))->getRequiredSystemDependencies('3.2.4', new Values())
        );
        $almalinux = new Extensions\http(self::createPHP(804), self::createOS(OS\AlmaLinux::class, 10));
        self::assertSame(
            [libcurl::HANDLE, libevent::HANDLE, libidn::HANDLE, libopenssl::HANDLE, libzlib::HANDLE],
            $almalinux->getRequiredSystemDependencies('2.6.0', new Values())
        );
        self::assertSame(
            [libbrotli::HANDLE, libcurl::HANDLE, libevent::HANDLE, libicu::HANDLE, libidn2::HANDLE, libidnkit::HANDLE, libopenssl::HANDLE, libzlib::HANDLE],
            $almalinux->getRequiredSystemDependencies('4.3.1', new Values())
        );
    }

    /**
     * @return array<array{string, list<string>}>
     */
    public static function provideMacOSConfigureArguments()
    {
        return [
            ['/sdk', ['--with-http-libcurl-dir=/opt/homebrew/opt/curl', '--with-http-libidn2-dir=/opt/homebrew/opt/libidn2', '--with-http-zlib-dir=/sdk/usr']],
            ['', ['--with-http-libcurl-dir=/opt/homebrew/opt/curl', '--with-http-libidn2-dir=/opt/homebrew/opt/libidn2']],
        ];
    }

    /**
     * @dataProvider provideMacOSConfigureArguments
     *
     * @param string $sdkPath
     * @param list<string> $expected
     *
     * @return void
     */
    public function testMacOS($sdkPath, array $expected)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->once()->andReturn([0, "/opt/homebrew\n"]);
        $macOS = M::mock(OS\MacOS::class);
        $macOS->shouldReceive('getFilesystem')->andReturn($fs);
        $macOS->shouldReceive('getSystemPackager')->andReturn(new Homebrew($macOS));
        $macOS->shouldReceive('getSDKPath')->andReturn($sdkPath);
        $http = new Extensions\http(self::createPHP(804), $macOS);
        self::assertSame(
            [libbrotli::HANDLE, libcurl::HANDLE, libevent::HANDLE, libicu::HANDLE, libidn2::HANDLE, libopenssl::HANDLE, libzlib::HANDLE],
            $http->getRequiredSystemDependencies('', new Values())
        );
        self::assertSame($expected, $http->getConfigureArguments(self::createBuildContext()));
        self::assertSame([], (new Extensions\http(self::createPHP(804), self::createOS(OS\Debian::class, OS\Debian::TRIXIE)))->getConfigureArguments(self::createBuildContext()));
    }
}
