<?php

namespace IPE\Test;

use IPE\Architecture;
use IPE\Test\Support\TestCase;

final class ArchitectureTest extends TestCase
{
    /**
     * @return array<string, array{non-empty-string}>
     */
    public static function provideConstants()
    {
        $result = [];
        foreach ((new \ReflectionClass(Architecture::class))->getConstants() as $name => $value) {
            if (is_string($value) && $value !== '') {
                $result[$name] = [$value];
            }
        }

        return $result;
    }

    /**
     * @dataProvider provideConstants
     *
     * @param non-empty-string $value
     *
     * @return void
     */
    public function testConstantIsRecognized($value)
    {
        self::assertSame($value, (new Architecture($value))->getName());
    }
}
