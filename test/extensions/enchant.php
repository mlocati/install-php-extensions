<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking enchant... ';

$broker = enchant_broker_init();
if ($broker === false || !is_array(enchant_broker_describe($broker))) {
    throw new TestFailedException('enchant_broker_describe() failed');
}

echo "passed.\n";
