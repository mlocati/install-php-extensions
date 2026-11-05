<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking rdkafka... ';

new RdKafka\Conf();

echo "passed.\n";
