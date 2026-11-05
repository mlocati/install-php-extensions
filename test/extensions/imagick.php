<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking imagick... ';

$missingFormats = array_diff(['BMP', 'GIF', 'JPG', 'PNG', 'SVG', 'TIFF'], array_map('strtoupper', Imagick::queryFormats()));
if ($missingFormats !== []) {
    throw new TestFailedException('Imagick does not support these formats: ' . implode(', ', $missingFormats));
}
$imagick = new Imagick();
$imagick->newImage(2, 1, 'white', 'png');
if ($imagick->getImageWidth() !== 2) {
    throw new TestFailedException('Imagick::getImageWidth() returned an unexpected value');
}

echo "passed.\n";
