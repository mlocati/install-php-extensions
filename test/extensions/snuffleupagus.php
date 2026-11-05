<?php

// The default rules of snuffleupagus forbid what _bootstrap.php does

echo 'Checking snuffleupagus... ';

if (!extension_loaded('snuffleupagus') || (string) ini_get('sp.configuration_file') === '') {
    echo "the extension is not loaded, or its rules have not been configured.\n";
    exit(1);
}

echo "passed.\n";
