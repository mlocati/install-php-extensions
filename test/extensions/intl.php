<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking intl... ';

foreach ([
    'en' => "\xC2\xA4#,##0.00",
    'it' => "#,##0.00\xC2\xA0\xC2\xA4",
] as $locale => $expected) {
    $actual = (new NumberFormatter($locale, NumberFormatter::CURRENCY))->getPattern();
    if ($actual !== $expected) {
        throw new TestFailedException("Wrong currency format for the locale {$locale} (expected: '{$expected}', actual: '{$actual}')");
    }
}

echo "passed.\n";
