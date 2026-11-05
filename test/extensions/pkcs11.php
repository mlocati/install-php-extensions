<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pkcs11... ';

$moduleInfo = (new Pkcs11\Module('/usr/lib/softhsm/libsofthsm2.so'))->getInfo();
if (!isset($moduleInfo['manufacturerID']) || $moduleInfo['manufacturerID'] !== 'SoftHSM') {
    throw new TestFailedException('Pkcs11\Module::getInfo() returned an unexpected value');
}

echo "passed.\n";
