<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking sodium... ';

// The old versions of the PECL package use a namespace
$bin2hex = function_exists('sodium_bin2hex') ? 'sodium_bin2hex' : 'Sodium\\bin2hex';
$actual = $bin2hex('a');
if ($actual !== '61') {
    throw new TestFailedException("{$bin2hex}() returned an unexpected value: " . var_export($actual, true));
}

echo "passed.\n";
