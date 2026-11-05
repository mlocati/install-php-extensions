<?php

namespace IPE\Test\Extension\Version;

use IPE\Extension\Version;
use IPE\Extension\Version\Stability;
use IPE\Test\Support\TestCase;

final class PeclTest extends TestCase
{
    /**
     * @return array{string, string, string}[]
     */
    public static function provideStabilityCases()
    {
        return [
            ['1.0.0', 'stable', Stability::STABLE],
            ['1.0.0', 'beta', Stability::BETA],
            ['1.0.0', 'alpha', Stability::ALPHA],
            ['1.0.0', 'devel', Stability::DEV],
            ['1.0.0', 'snapshot', Stability::DEV],
            ['3.2.17RC1', 'stable', Stability::RC],
            ['1.0.0beta2', 'stable', Stability::BETA],
            ['1.0.0alpha1', 'stable', Stability::ALPHA],
            ['2.0.0RC1', 'beta', Stability::BETA],
            ['1.0.0RC1', 'alpha', Stability::ALPHA],
        ];
    }

    /**
     * @dataProvider provideStabilityCases
     *
     * @param non-empty-string $version
     * @param non-empty-string $peclStability
     * @param string $expectedStability
     *
     * @return void
     */
    public function testStability($version, $peclStability, $expectedStability)
    {
        $pecl = new Version\Pecl('foo', $version, $peclStability);
        self::assertSame($expectedStability, $pecl->getStability());
    }
}
