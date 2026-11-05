<?php

namespace IPE\Test\Extension\Version;

use IPE\Extension\Version\Stability;
use IPE\Test\Support\TestCase;

final class StabilityTest extends TestCase
{
    /**
     * @return void
     */
    public function testAllConstantsDefined()
    {
        $class = new \ReflectionClass(Stability::class);
        $expected = [];
        foreach ($class->getConstants() as $name => $value) {
            if (is_string($value) && $value !== '') {
                switch ($name) {
                    default:
                        $expected[] = $value;
                        break;
                }
            }
        }
        sort($expected, \SORT_STRING);
        $actual = Stability::getAll();
        sort($actual, \SORT_STRING);
        self::assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideNormalizationCases
     *
     * @param non-empty-string $value
     * @param string $expectedNormalized
     *
     * @return void
     */
    public function testNormalize($value, $expectedNormalized)
    {
        $actualNormalized = Stability::normalize($value);
        self::assertSame($expectedNormalized, $actualNormalized);
    }

    /**
     * @return list<array{non-empty-string, string}>
     */
    public static function provideNormalizationCases()
    {
        $result = [
            ['foo', ''],
            ['stable ', ''],
        ];
        foreach (Stability::getAll() as $value) {
            $result[] = [$value, $value];
            $result[] = [strtolower($value), $value];
            $result[] = [strtoupper($value), $value];
        }

        return $result;
    }

    /**
     * @dataProvider provideCompareCases
     *
     * @param non-empty-string $a
     * @param non-empty-string $b
     * @param int $expectedResult
     *
     * @return void
     */
    public function testCompare($a, $b, $expectedResult)
    {
        $actualResult = Stability::compare($a, $b);
        self::assertTrue(in_array($actualResult, [-1, 0, 1], true));
        self::assertSame($expectedResult, $actualResult);
        $commutativeActualResult = Stability::compare($b, $a);
        self::assertTrue(in_array($commutativeActualResult, [-1, 0, 1], true));
        self::assertSame(-$expectedResult, $commutativeActualResult);
    }

    /**
     * @return list<array{non-empty-string, non-empty-string, int}>
     */
    public static function provideCompareCases()
    {
        $result = [
            ['foo', 'bar', 0],
            ['foo', Stability::DEV, -1],
            // ---
            [Stability::DEV, Stability::ALPHA, -1],
            [Stability::DEV, Stability::BETA, -1],
            [Stability::DEV, Stability::RC, -1],
            [Stability::DEV, Stability::STABLE, -1],
            // ---
            [Stability::ALPHA, Stability::BETA, -1],
            [Stability::ALPHA, Stability::RC, -1],
            [Stability::ALPHA, Stability::STABLE, -1],
            // ---
            [Stability::BETA, Stability::RC, -1],
            [Stability::BETA, Stability::STABLE, -1],
            // ---
            [Stability::RC, Stability::STABLE, -1],
        ];
        foreach (Stability::getAll() as $value) {
            $result[] = [$value, $value, 0];
            if ($value !== strtolower($value)) {
                $result[] = [strtolower($value), $value, -1];
            } elseif ($value !== strtoupper($value)) {
                $result[] = [strtoupper($value), $value, -1];
            }
        }

        return $result;
    }
}
