<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking xmldiff... ';

if (!class_exists('XMLDiff\\DOM')) {
    throw new TestFailedException('The class XMLDiff\\DOM is not available');
}

echo "passed.\n";
