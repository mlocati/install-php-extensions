<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking solr... ';

if (!class_exists('SolrClient')) {
    throw new TestFailedException('The class SolrClient is not available');
}

echo "passed.\n";
