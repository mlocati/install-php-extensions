<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking dba... ';

if (!in_array('db4', dba_handlers(false), true)) {
    throw new TestFailedException("DBA does not support the db4 handler.\nSupported handlers:\n- " . implode("\n- ", dba_handlers(false)));
}
$file = tempnam(sys_get_temp_dir(), 'ipe');
try {
    $db = dba_open($file, 'n', 'db4');
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
