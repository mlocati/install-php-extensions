<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pkcs11... ';

foreach (['Pkcs11\Module', 'Pkcs11\Session', 'Pkcs11\Key'] as $class) {
    if (!class_exists($class)) {
        throw new TestFailedException("The class {$class} is not defined");
    }
}

try {
    new Pkcs11\Module('/nonexistent/module.so');
    $exception = null;
} catch (Pkcs11\Exception $x) {
    $exception = $x;
}
if ($exception === null) {
    throw new TestFailedException('Pkcs11\Module accepted a module that does not exist');
}

// SoftHSM isn't installed when the extension comes from a system package
foreach (['/usr/lib/softhsm/libsofthsm2.so', '/usr/lib64/pkcs11/libsofthsm2.so'] as $path) {
    if (is_file($path)) {
        $moduleInfo = (new Pkcs11\Module($path))->getInfo();
        if (!isset($moduleInfo['manufacturerID']) || $moduleInfo['manufacturerID'] !== 'SoftHSM') {
            throw new TestFailedException('Pkcs11\Module::getInfo() returned an unexpected value');
        }
        break;
    }
}

echo "passed.\n";
