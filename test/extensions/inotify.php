<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking inotify... ';

$fd = inotify_init();
if (!is_resource($fd)) {
    throw new TestFailedException('inotify_init() did not return a resource');
}
fclose($fd);

echo "passed.\n";
