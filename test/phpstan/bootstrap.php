<?php

// Constants that the test bootstrap defines at runtime.
const IPE_ROOT_DIR = __DIR__ . '/../..';
const IPE_TEST_TMPDIR = IPE_ROOT_DIR . '/test/tmp';
const IPE_TEST_WEBSERVER_PORT = 0;
const IPE_TEST_PROXYSERVER_PORT = 0;

// Defined by bin/install-php-extensions; listed in dynamicConstantNames so that PHPStan types it as string
const IPE_VERSION = '';
const IPE_CLI_ROOT_DIR = '';
