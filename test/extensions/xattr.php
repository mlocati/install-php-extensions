<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking xattr... ';

if (!is_array(xattr_list(__FILE__))) {
    throw new TestFailedException('xattr_list() did not return an array');
}

echo "passed.\n";
