<?php

namespace IPE\Test\Service;

use IPE\Service\SizeFormatter;
use IPE\Test\Support\TestCase;

final class SizeFormatterTest extends TestCase
{
    /**
     * @return list<array{int, string}>
     */
    public static function provideCases()
    {
        return [
            [0, '0 bytes'],
            [1, '1 byte'],
            [2, '2 bytes'],
            [1023, '1023 bytes'],
            [1024, '1.00 KB'],
            [1536, '1.50 KB'],
            [1024 * 1024 - 1, '1024.00 KB'],
            [1024 * 1024, '1.00 MB'],
            [12939428, '12.34 MB'],
            [1024 * 1024 * 1024, '1.00 GB'],
            [1024 * 1024 * 1024 * 1024, '1.00 TB'],
            [1024 * 1024 * 1024 * 1024 * 1024, '1024.00 TB'],
            [-1536, '-1.50 KB'],
        ];
    }

    /**
     * @dataProvider provideCases
     *
     * @param int $bytes
     * @param string $expected
     *
     * @return void
     */
    public function testFormat($bytes, $expected)
    {
        self::assertSame($expected, SizeFormatter::format($bytes));
    }
}
