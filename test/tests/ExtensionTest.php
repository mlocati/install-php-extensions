<?php

namespace IPE\Test;

use IPE\Extension;
use IPE\Test\Support\TestCase;

final class ExtensionTest extends TestCase
{
    /**
     * @return array<array{string, string, bool}>
     */
    public static function provideIsVersionAtLeast()
    {
        return [
            ['', '1.0.0', true],
            ['1.0.0', '1.0.0', true],
            ['1.2.3', '1.0.0', true],
            ['0.9.9', '1.0.0', false],
            ['1.0.0RC1', '1.0.0', false],
        ];
    }

    /**
     * @dataProvider provideIsVersionAtLeast
     *
     * @param string $version
     * @param non-empty-string $minimum
     * @param bool $expected
     *
     * @return void
     */
    public function testIsVersionAtLeast($version, $minimum, $expected)
    {
        $method = new \ReflectionMethod(Extension::class, 'isVersionAtLeast');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        self::assertSame($expected, $method->invoke(null, $version, $minimum));
    }
}
