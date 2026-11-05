<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking blackfire... ';

if (!extension_loaded('blackfire')) {
    throw new TestFailedException('The extension blackfire is not loaded');
}
ob_start();
(new ReflectionExtension('blackfire'))->info();
$info = ob_get_clean();
if (strpos($info, 'Blackfire => enabled') === false) {
    throw new TestFailedException('The Blackfire PHP Probe is disabled');
}

echo "passed.\n";
