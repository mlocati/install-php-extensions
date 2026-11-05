<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking calendar... ';

$actual = cal_days_in_month(CAL_GREGORIAN, 2, 2024);
if ($actual !== 29) {
    throw new TestFailedException('cal_days_in_month() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
