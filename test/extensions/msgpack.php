<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking msgpack... ';

$actual = msgpack_unpack(msgpack_pack([1, 'a']));
if ($actual !== [1, 'a']) {
    throw new TestFailedException('msgpack_unpack() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
