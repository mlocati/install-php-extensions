<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking statgrab... ';

$stats = sg_general_stats();
if (!is_array($stats) || !isset($stats['ncpus']) || $stats['ncpus'] < 1) {
    throw new TestFailedException('sg_general_stats() returned an unexpected value');
}

echo "passed.\n";
