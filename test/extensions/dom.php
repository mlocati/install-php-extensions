<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking dom... ';

$document = new DOMDocument();
$document->loadXML('<root>test</root>');
$actual = $document->documentElement->textContent;
if ($actual !== 'test') {
    throw new TestFailedException('DOMDocument returned an unexpected value: ' . var_export($actual, true));
}
if (class_exists('Dom\HTMLDocument')) {
    $className = 'Dom\HTMLDocument';
    $document = $className::createFromString('<!DOCTYPE html><p class="x">test</p>');
    $actual = $document->querySelector('p.x')->textContent;
    if ($actual !== 'test') {
        throw new TestFailedException('Dom\HTMLDocument returned an unexpected value: ' . var_export($actual, true));
    }
}

echo "passed.\n";
