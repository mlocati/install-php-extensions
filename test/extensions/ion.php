<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking ion... ';

if (!ion\unserialize(ion\serialize(ion\Symbol\PHP::asTable())) instanceof ion\Symbol\Table) {
    throw new TestFailedException('ion\unserialize() did not return an ion\Symbol\Table instance');
}

echo "passed.\n";
