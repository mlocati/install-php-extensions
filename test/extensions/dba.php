<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking dba... ';

// The dba packages of some systems (like AlmaLinux 9) don't support db4
$handler = in_array('db4', dba_handlers(false), true) ? 'db4' : 'flatfile';
$file = tempnam(sys_get_temp_dir(), 'ipe');
try {
    $db = dba_open($file, 'n', $handler);
    if ($db === false) {
        throw new TestFailedException('dba_open() failed');
    }
    dba_insert('key', 'value', $db);
    $actual = dba_fetch('key', $db);
    dba_close($db);
    if ($actual !== 'value') {
        throw new TestFailedException('dba_fetch() returned an unexpected value: ' . var_export($actual, true));
    }
} finally {
    unlink($file);
}

echo "passed.\n";
