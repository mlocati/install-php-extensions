<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking saxon... ';

// Without the license flag, the recent EE and PE editions behave like HE (and their version says HE).
// Not a temporary object: destroying the last processor corrupts the memory with SaxonC 12.3.
$processor = new Saxon\SaxonProcessor(true);
$version = $processor->version();
if (!preg_match('/SaxonC-(?<edition>\w+) \d[.\d]*/', $version, $matches)) {
    throw new TestFailedException("Failed to parse the SaxonC version: {$version}");
}
$edition = getInstallOption('saxon:edition');
if ($edition !== null && $matches['edition'] !== $edition) {
    throw new TestFailedException("The edition {$edition} has been requested, but {$matches['edition']} has been installed ({$version})");
}

echo "passed.\n";
