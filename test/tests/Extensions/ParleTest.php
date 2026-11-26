<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\parle;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ParleTest extends TestCase
{
    /**
     * @return void
     */
    public function testConfigureArguments()
    {
        $php = M::mock(PHPInfo::class);
        $context = self::createBuildContext('0.8.5');
        self::assertSame(['--enable-parle-utf32'], (new parle($php, M::mock(OS\Debian::class)))->getConfigureArguments($context));
        self::assertSame([], (new parle($php, M::mock(OS\Debian::class)))->getConfigureArguments(self::createBuildContext('0.8.3')));
        self::assertSame([], (new parle($php, M::mock(OS\MacOS::class)))->getConfigureArguments($context));
    }
}
