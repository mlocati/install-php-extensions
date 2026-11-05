<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking cassandra... ';

$uuid = new Cassandra\Uuid();
if ($uuid->version() !== 4) {
    throw new TestFailedException('Cassandra\Uuid::version() returned an unexpected value: ' . $uuid->version());
}
$bigint = new Cassandra\Bigint('9223372036854775807');
if ((string) $bigint !== '9223372036854775807') {
    throw new TestFailedException('Cassandra\Bigint returned an unexpected value: ' . $bigint);
}
$cluster = Cassandra::cluster()->withContactPoints('127.0.0.1')->withPort(9042)->build();
if (!$cluster instanceof Cassandra\Cluster) {
    throw new TestFailedException('Cassandra\Cluster\Builder::build() did not return a Cassandra\Cluster instance');
}

echo "passed.\n";
