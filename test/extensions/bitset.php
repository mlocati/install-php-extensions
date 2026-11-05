<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking bitset... ';

$bitset = new BitSet();
$bitset->set(3);
if ($bitset->get(3) !== true || $bitset->get(2) !== false) {
    throw new TestFailedException('BitSet does not work as expected');
}

echo "passed.\n";
