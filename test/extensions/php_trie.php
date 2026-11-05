<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking php_trie... ';

if (!class_exists('HatTrie')) {
    throw new TestFailedException('The class HatTrie is not available');
}

echo "passed.\n";
