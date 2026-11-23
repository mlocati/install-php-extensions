<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\ev;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class EvTest extends TestCase
{
    /**
     * @return array<array{string, list<string>}>
     */
    public static function provideRequiredExtensions()
    {
        return [
            ["'./configure' '--enable-sockets=shared' '--enable-fpm'", ['sockets']],
            ["'./configure' '--enable-sockets'", ['sockets']],
            ['./configure --enable-sockets', ['sockets']],
            ["'./configure' '--enable-sockets=no'", []],
            ["'./configure' '--enable-sockets-foo'", []],
            ["'./configure' '--enable-fpm'", []],
            ['', []],
        ];
    }

    /**
     * @dataProvider provideRequiredExtensions
     *
     * @param string $configureCommand
     * @param list<string> $expected
     *
     * @return void
     */
    public function testRequiredExtensions($configureCommand, array $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getConfigureCommand')->andReturn($configureCommand);
        self::assertSame($expected, (new ev($php, M::mock(OS::class)))->getRequiredExtensions(''));
    }
}
