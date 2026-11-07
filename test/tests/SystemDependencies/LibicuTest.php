<?php

namespace IPE\Test\SystemDependencies;

use IPE\Installer\State;
use IPE\Option\Validator;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\libicu;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class LibicuTest extends TestCase
{
    /**
     * @param class-string<\IPE\OS\Posix> $class
     * @param int $version
     * @param bool $englishOnly
     *
     * @return \IPE\SystemDependencies\libicu
     */
    private static function createDependency($class, $version, $englishOnly)
    {
        $os = M::mock($class);
        self::assertInstanceOf(OS\Posix::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($version);

        return new libicu(M::mock(PHPInfo::class), $os, (new Validator(new \IPE\Logger()))->validate($englishOnly ? [libicu::OPTION_EN_ONLY => true] : [], libicu::getOptions(), false, libicu::HANDLE));
    }

    /**
     * @return void
     */
    public function testSystemPackages()
    {
        $state = M::mock(State::class);
        $full = self::createDependency(OS\Alpine::class, 316, false)->getSystemPackages($state);
        self::assertSame(['icu-libs', 'icu-data-full'], $full->getPersistent());
        self::assertSame(['icu-dev'], $full->getVolatile());
        self::assertSame(['icu-libs'], self::createDependency(OS\Alpine::class, 316, true)->getSystemPackages($state)->getPersistent());
        self::assertSame(['icu-libs'], self::createDependency(OS\Alpine::class, 315, false)->getSystemPackages($state)->getPersistent());
        $debian = self::createDependency(OS\Debian::class, OS\Debian::TRIXIE, true)->getSystemPackages($state);
        self::assertSame(['^libicu[0-9]+$'], $debian->getPersistent());
        self::assertSame(['libicu-dev'], $debian->getVolatile());
    }
}
