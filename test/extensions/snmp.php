<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking snmp... ';

if (!class_exists('SNMP')) {
    throw new TestFailedException('The class SNMP is missing');
}
if (!function_exists('snmp_get_quick_print')) {
    throw new TestFailedException('The function snmp_get_quick_print() is missing');
}

echo "passed.\n";
