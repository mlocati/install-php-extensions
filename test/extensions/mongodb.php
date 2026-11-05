<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking mongodb... ';

new MongoDB\BSON\ObjectId();
new MongoDB\Driver\Manager('mongodb://localhost:27017');

echo "passed.\n";
