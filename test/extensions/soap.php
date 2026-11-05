<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking soap... ';

new SoapClient(null, ['location' => 'http://localhost/', 'uri' => 'urn:ipe']);

echo "passed.\n";
