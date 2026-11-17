<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking stomp... ';

ob_start();
(new ReflectionExtension('stomp'))->info();
// The packages of Debian and Ubuntu are built without SSL
if (strpos(ob_get_clean(), 'SSL Support => enabled') === false && getInstalledExtensionOrigin('stomp') !== 'system package') {
    throw new TestFailedException('stomp has not been compiled with SSL support');
}

echo "passed.\n";
