<?php

namespace IPE\Test\Extension;

use IPE\Exception;
use IPE\Extension\UnsupportedEnvironment;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\Container;
use IPE\Test\Support\OSFactoryTest\FakeFactory;
use IPE\Test\Support\OSFactoryTest\FakeOS;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class UnsupportedEnvironmentTest extends TestCase
{
    /**
     * @return array<array{string, bool, bool, bool, bool}> the environment, and if it matches PHP 7.2 on Alpine 3.9, on Alpine 3.10, on Debian 8 and on Ubuntu 24.04
     */
    public static function provideMatchingCases()
    {
        return [
            ['alpine', true, true, false, false],
            ['alpine3.9', true, false, false, false],
            ['alpine3.10', false, true, false, false],
            ['alpine3.1', false, false, false, false],
            ['alpine-php7', true, true, false, false],
            ['alpine-php7.2', true, true, false, false],
            ['alpine-php7.3', false, false, false, false],
            ['alpine-php8', false, false, false, false],
            ['alpine3.10-php7.2', false, true, false, false],
            ['alpine3.10-php7', false, true, false, false],
            ['alpine3.10-php7.3', false, false, false, false],
            ['debian', false, false, true, false],
            ['debian8', false, false, true, false],
            ['debian12', false, false, false, false],
            ['debian-php7', false, false, true, false],
            ['debian8-php7.2', false, false, true, false],
            ['debian8-php7.3', false, false, false, false],
            ['ubuntu', false, false, false, true],
            ['ubuntu24.04', false, false, false, true],
            ['ubuntu22.04', false, false, false, false],
            ['ubuntu24.04-php7', false, false, false, true],
            ['windows', false, false, false, false],
            ['alpine3.9..3.10', true, true, false, false],
            ['alpine3.8..3.9', true, false, false, false],
            ['alpine3.10..3.11', false, true, false, false],
            ['alpine3.7..3.8', false, false, false, false],
            ['alpine3.11..3.12', false, false, false, false],
            ['alpine3.9..3.10-php7.2', true, true, false, false],
            ['alpine3.9..3.10-php7.3', false, false, false, false],
            ['debian7..9', false, false, true, false],
            ['ubuntu22.04..24.04', false, false, false, true],
        ];
    }

    /**
     * @dataProvider provideMatchingCases
     *
     * @param string $environment
     * @param bool $onAlpine39
     * @param bool $onAlpine310
     * @param bool $onDebian8
     * @param bool $onUbuntu2404
     *
     * @return void
     */
    public function testMatches($environment, $onAlpine39, $onAlpine310, $onDebian8, $onUbuntu2404)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(702);
        $alpine39 = M::mock(OS\Alpine::class);
        $alpine39->shouldReceive('getNumericVersion')->andReturn(309);
        $alpine310 = M::mock(OS\Alpine::class);
        $alpine310->shouldReceive('getNumericVersion')->andReturn(310);
        $debian8 = M::mock(OS\Debian::class);
        $debian8->shouldReceive('getNumericVersion')->andReturn(8);
        $ubuntu2404 = M::mock(OS\Ubuntu::class);
        $ubuntu2404->shouldReceive('getNumericVersion')->andReturn(2404);
        $subject = new UnsupportedEnvironment(new OS\Factory(new Container(), new \IPE\Logger()));
        self::assertTrue($subject->isValid($environment));
        self::assertSame($onAlpine39, $subject->matches($environment, $php, $alpine39));
        self::assertSame($onAlpine310, $subject->matches($environment, $php, $alpine310));
        self::assertSame($onDebian8, $subject->matches($environment, $php, $debian8));
        self::assertSame($onUbuntu2404, $subject->matches($environment, $php, $ubuntu2404));
    }

    /**
     * @return array<array{string}>
     */
    public static function provideInvalidCases()
    {
        return [
            ['alpine3'],
            ['alpine3.14.2'],
            ['debian8.1'],
            ['ubuntu24'],
            ['7.2-alpine'],
            ['jessie'],
            ['fake1'],
            ['alpine-php'],
            [''],
            ['zts'],
            ['alpine-zts-php7.2'],
            ['alpine-nts-php7.2'],
            ['alpine-ts'],
            ['alpine3.9-zts-nts'],
            ['alpine3.16..3.10'],
            ['alpine3.10..3.10'],
            ['alpine..3.10'],
            ['alpine3.10..'],
            ['alpine3.10..3'],
            ['alpine3.9..3.10..3.11'],
            ['debian8..8.1'],
        ];
    }

    /**
     * @dataProvider provideInvalidCases
     *
     * @param string $environment
     *
     * @return void
     */
    public function testInvalid($environment)
    {
        $subject = new UnsupportedEnvironment(new OS\Factory(new Container(), new \IPE\Logger()));
        self::assertFalse($subject->isValid($environment));
        $this->expectException(Exception\InvalidArgument::class);
        $subject->matches($environment, M::mock(PHPInfo::class), M::mock(OS\Alpine::class));
    }

    /**
     * @return array<array{string, bool, bool}> the environment, and if it matches PHP 7.2 on Alpine 3.9 when PHP is thread-safe and when it isn't
     */
    public static function provideThreadSafetyCases()
    {
        return [
            ['alpine', true, true],
            ['alpine-zts', true, false],
            ['alpine3.9-zts', true, false],
            ['alpine3.10-zts', false, false],
            ['alpine-php7-zts', true, false],
            ['alpine3.9-php7.2-zts', true, false],
            ['alpine3.9-php7.3-zts', false, false],
            ['debian-zts', false, false],
            ['alpine-nts', false, true],
            ['alpine3.9-nts', false, true],
            ['alpine3.10-nts', false, false],
            ['alpine-php7-nts', false, true],
            ['alpine3.9-php7.2-nts', false, true],
            ['alpine3.9-php7.3-nts', false, false],
            ['debian-nts', false, false],
            ['alpine3.8..3.10-zts', true, false],
            ['alpine3.8..3.10-nts', false, true],
            ['alpine3.8..3.10-php7.2-zts', true, false],
            ['alpine3.10..3.11-zts', false, false],
        ];
    }

    /**
     * @dataProvider provideThreadSafetyCases
     *
     * @param string $environment
     * @param bool $whenThreadSafe
     * @param bool $whenNotThreadSafe
     *
     * @return void
     */
    public function testThreadSafety($environment, $whenThreadSafe, $whenNotThreadSafe)
    {
        $os = M::mock(OS\Alpine::class);
        $os->shouldReceive('getNumericVersion')->andReturn(309);
        $subject = new UnsupportedEnvironment(new OS\Factory(new Container(), new \IPE\Logger()));
        self::assertTrue($subject->isValid($environment));
        foreach ([[true, $whenThreadSafe], [false, $whenNotThreadSafe]] as $case) {
            $php = M::mock(PHPInfo::class);
            $php->shouldReceive('getMajorMinorVersion')->andReturn(702);
            $php->shouldReceive('isThreadSafe')->andReturn($case[0]);
            self::assertSame($case[1], $subject->matches($environment, $php, $os));
        }
    }

    /**
     * @return void
     */
    public function testWithExtendedFactory()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(702);
        $subject = new UnsupportedEnvironment(new FakeFactory(new Container(), new \IPE\Logger()));
        $os = new FakeOS('5');
        self::assertTrue($subject->matches('fakeos', $php, $os));
        self::assertTrue($subject->matches('fakeos5', $php, $os));
        self::assertFalse($subject->matches('fakeos6', $php, $os));
        self::assertFalse($subject->isValid('fakeos6.1'));
        self::assertTrue($subject->isValid('alpine3.14'));
    }
}
