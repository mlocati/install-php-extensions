<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking opentelemetry... ';

if (!function_exists('OpenTelemetry\Instrumentation\hook')) {
    throw new TestFailedException('The function OpenTelemetry\Instrumentation\hook() is not available');
}

echo "passed.\n";
