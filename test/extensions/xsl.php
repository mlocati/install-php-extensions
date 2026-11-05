<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking xsl... ';

$stylesheet = new DOMDocument();
$stylesheet->loadXML('<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"><xsl:output method="text"/><xsl:template match="/"><xsl:value-of select="root"/></xsl:template></xsl:stylesheet>');
$document = new DOMDocument();
$document->loadXML('<root>test</root>');
$processor = new XSLTProcessor();
$processor->importStylesheet($stylesheet);
$actual = $processor->transformToXml($document);
if ($actual !== 'test') {
    throw new TestFailedException('XSLTProcessor returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
