<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking ioncube_loader... ';

foreach ([false, true] as $zendExtensions) {
    $extensions = get_loaded_extensions($zendExtensions);
    if (preg_grep('/^(the )?ioncube (php )?loader/i', $extensions) === []) {
        throw new TestFailedException('The ionCube Loader ' . ($zendExtensions ? 'Zend extension' : 'PHP module') . " is not loaded.\nLoaded extensions:\n- " . implode("\n- ", $extensions));
    }
}

echo "passed.\n";
