<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking tokenizer... ';

$tokens = token_get_all('<?php echo 1;');
if (!is_array($tokens) || !isset($tokens[0][0]) || $tokens[0][0] !== T_OPEN_TAG) {
    throw new TestFailedException('token_get_all() returned an unexpected value: ' . var_export($tokens, true));
}

echo "passed.\n";
