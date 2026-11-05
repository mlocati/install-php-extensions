<?php

namespace IPE\Test\Extensions;

use IPE\Extensions;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\libcurl;
use IPE\SystemDependencies\libevent;
use IPE\SystemDependencies\libgcrypt;
use IPE\SystemDependencies\libicu;
use IPE\SystemDependencies\libidn;
use IPE\SystemDependencies\libidn2;
use IPE\SystemDependencies\libidnkit;
use IPE\SystemDependencies\libopenssl;
use IPE\SystemDependencies\libzlib;
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
        $alpine = self::createOS(OS\Alpine::class, 323);
        self::assertSame(
            [libcurl::HANDLE, libevent::HANDLE, libidn::HANDLE, libzlib::HANDLE],
            (new Extensions\http(self::createPHP(506), $alpine))->getRequiredSystemDependencies('', new Values())
        );
        self::assertSame(
            [libcurl::HANDLE, libevent::HANDLE, libicu::HANDLE, libidn::HANDLE, libidnkit::HANDLE, libzlib::HANDLE],
            (new Extensions\http(self::createPHP(804), $alpine))->getRequiredSystemDependencies('', new Values())
        );
        $debian = self::createOS(OS\Debian::class, OS\Debian::VERSION_TRIXIE);
        self::assertSame(
            [libcurl::HANDLE, libevent::HANDLE, libidn::HANDLE, libopenssl::HANDLE, libzlib::HANDLE],
            (new Extensions\http(self::createPHP(506), $debian))->getRequiredSystemDependencies('', new Values())
        );
        self::assertSame(
            [libcurl::HANDLE, libevent::HANDLE, libgcrypt::HANDLE, libicu::HANDLE, libidn2::HANDLE, libidnkit::HANDLE, libopenssl::HANDLE, libzlib::HANDLE],
            (new Extensions\http(self::createPHP(804), $debian))->getRequiredSystemDependencies('', new Values())
        );
    }
}
