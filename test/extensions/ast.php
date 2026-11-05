<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking ast... ';

$versions = ast\get_supported_versions();
$node = ast\parse_code('<?php echo 1;', end($versions));
if (!$node instanceof ast\Node) {
    throw new TestFailedException('ast\parse_code() did not return an ast\Node instance');
}

echo "passed.\n";
