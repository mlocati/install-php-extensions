<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pspell... ';

// The system packages of pspell don't install any dictionary, and some systems (like Enterprise Linux 10) don't provide the English one
try {
    $english = pspell_new('en');
} catch (ErrorException $x) {
    $english = false;
}
if ($english === false) {
    if (!pspell_config_create('en')) {
        throw new TestFailedException('pspell_config_create() failed');
    }
} else {
    if (pspell_check($english, 'Goodbye') !== true) {
        throw new TestFailedException('pspell failed to detect a correct English word');
    }
    if (pspell_check($english, 'Xqzwv') !== false) {
        throw new TestFailedException('pspell failed to detect a wrong English word');
    }
}

echo "passed.\n";
