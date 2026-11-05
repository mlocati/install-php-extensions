<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking parle... ';

if (!class_exists('Parle\\Lexer')) {
    throw new TestFailedException('The class Parle\\Lexer is not available');
}

echo "passed.\n";
