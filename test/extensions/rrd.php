<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking rrd... ';

$file = tempnam(sys_get_temp_dir(), 'rrd');
$created = rrd_create($file, ['--step', '300', '--start', 0, 'DS:input:COUNTER:900:0:U', 'RRA:AVERAGE:0.5:1:1000']);
unlink($file);
if ($created !== true) {
    throw new TestFailedException('rrd_create() failed');
}

echo "passed.\n";
