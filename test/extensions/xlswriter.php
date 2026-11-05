<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking xlswriter... ';

if (!class_exists('Vtiful\\Kernel\\Excel')) {
    throw new TestFailedException('The class Vtiful\\Kernel\\Excel is not available');
}

echo "passed.\n";
