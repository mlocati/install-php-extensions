<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking yajl_stream... ';

$file = tempnam(sys_get_temp_dir(), 'yajl');
file_put_contents($file, '{"meta":{"items":[0]},"data":{"items":[{"id":1},null,{"id":3}]}}');
$items = [];
foreach (new YajlStream\ArrayParser($file, '/data/items') as $key => $item) {
    $items[$key] = $item;
}
unlink($file);
if ($items !== [['id' => 1], null, ['id' => 3]]) {
    throw new TestFailedException('YajlStream\ArrayParser returned an unexpected value: ' . json_encode($items));
}

echo "passed.\n";
