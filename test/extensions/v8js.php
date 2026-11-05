<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking v8js... ';

$context = new V8Js();
$context->greeting = 'world';
ob_start();
$return = $context->executeString('var s = "Hello, " + PHP.greeting + "!"; print(s); s.length;');
$printed = ob_get_clean();
if ($printed !== 'Hello, world!' || $return !== 13) {
    throw new TestFailedException('V8Js::executeString() returned an unexpected value: ' . var_export([$printed, $return], true));
}

echo "passed.\n";
