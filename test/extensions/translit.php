<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking translit... ';

if (!is_array(transliterate_filters_get())) {
    throw new TestFailedException('transliterate_filters_get() did not return an array');
}

echo "passed.\n";
