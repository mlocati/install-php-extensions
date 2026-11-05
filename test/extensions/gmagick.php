<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking gmagick... ';

$gmagick = new Gmagick();
$gmagick->newimage(2, 1, 'white', 'png');
if ($gmagick->getimagewidth() !== 2) {
    throw new TestFailedException('Gmagick::getimagewidth() returned an unexpected value');
}

echo "passed.\n";
