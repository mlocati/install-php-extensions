<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pspell... ';

$english = pspell_new('en');
if (pspell_check($english, 'Goodbye') !== true) {
    throw new TestFailedException('pspell failed to detect a correct English word');
}
if (pspell_check($english, 'Xqzwv') !== false) {
    throw new TestFailedException('pspell failed to detect a wrong English word');
}

echo "passed.\n";
