<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking sourceguardian... ';

foreach ([false, true] as $zendExtensions) {
    $extensions = get_loaded_extensions($zendExtensions);
    if (preg_grep('/^sourceguardian$/i', $extensions) === []) {
        throw new TestFailedException('The SourceGuardian ' . ($zendExtensions ? 'Zend extension' : 'PHP module') . " is not loaded.\nLoaded extensions:\n- " . implode("\n- ", $extensions));
    }
}

echo "passed.\n";
