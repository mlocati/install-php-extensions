<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking imap... ';

$actual = imap_rfc822_write_address('user', 'example.com', '');
if ($actual !== 'user@example.com') {
    throw new TestFailedException('imap_rfc822_write_address() returned an unexpected value: ' . var_export($actual, true));
}
ob_start();
(new ReflectionExtension('imap'))->info();
$info = ob_get_clean();
if (strpos($info, 'SSL Support => enabled') === false) {
    throw new TestFailedException('The imap extension does not support SSL');
}

echo "passed.\n";
