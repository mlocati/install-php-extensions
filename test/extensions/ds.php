<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking ds... ';

$class = class_exists('Ds\Set') ? 'Ds\Set' : 'Ds\Vector';
$collection = new $class(['a', 'b', 'c']);
if ($collection->join("\t") !== "a\tb\tc") {
    throw new TestFailedException($class . '::join() does not work correctly');
}

echo "passed.\n";
