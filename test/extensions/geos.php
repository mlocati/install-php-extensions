<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking geos... ';

$reader = new GEOSWKTReader();
$point = $reader->read('POINT(1 2)');
if ($point->getX() != 1 || $point->getY() != 2) {
    throw new TestFailedException('GEOSWKTReader::read() returned an unexpected geometry');
}

echo "passed.\n";
