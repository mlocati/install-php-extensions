<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking tensor... ';

if (!class_exists('Tensor\\Matrix')) {
    throw new TestFailedException('The class Tensor\\Matrix is not available');
}

echo "passed.\n";
