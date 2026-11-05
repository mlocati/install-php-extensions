<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking judy... ';

$judy = new Judy(Judy::BITSET);
$judy[1] = false;
$judy[2] = true;
if ($judy[1] !== false || $judy[2] !== true) {
    throw new TestFailedException('Unexpected values from the Judy extension');
}

echo "passed.\n";
