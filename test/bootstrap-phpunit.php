<?php

use IPE\Test\Support;

require_once __DIR__ . '/bootstrap.php';

$bootstrap = static function () {
    // Just in case tests aren't executed with the bundled PHPUnit
    if (!class_exists(Support\TestCaseBase::class)) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    // Create the IPE\Test\Support\TestCase alias class, compatible with the current PHPUnit version
    $phpUnitVersion = class_exists('PHPUnit\\Runner\\Version') ? PHPUnit\Runner\Version::id() : '';
    if ($phpUnitVersion !== '' && version_compare($phpUnitVersion, '9') >= 0) {
        class_alias(Support\TestCase9::class, Support\TestCase::class);
    } elseif ($phpUnitVersion !== '' && version_compare($phpUnitVersion, '6') >= 0) {
        class_alias(Support\TestCase6::class, Support\TestCase::class);
    } else {
        class_alias(Support\TestCase4::class, Support\TestCase::class);
    }

    // Create the IPE\Test\Support\PSRLogger alias class, compatible with the current psr/log version
    $psrLoggerClass = Support\Log\PSRLogger1::class;
    if (PHP_VERSION_ID > 70000) {
        $psrInterface = new ReflectionClass(Psr\Log\LoggerInterface::class);
        $psrLogMethod = $psrInterface->getMethod('log');
        $psrLogMethodParams = $psrLogMethod->getParameters();
        $psrLogMethodParam = $psrLogMethodParams[1];
        if ($psrLogMethodParam->hasType()) {
            $psrLoggerClass = Support\Log\PSRLogger23::class;
        }
    }
    class_alias($psrLoggerClass, Support\PSRLogger::class);

    // Let's create a temporary directory that will be used by the tests
    // The value must be a constant expression, so that PHPStan can type it
    define('IPE_TEST_TMPDIR', __DIR__ . DIRECTORY_SEPARATOR . 'tmp');
    $fs = new IPE\Filesystem\Local(new \IPE\Logger());
    if ($fs->isDirectory(IPE_TEST_TMPDIR)) {
        $fs->deleteDirectory(IPE_TEST_TMPDIR);
    }
    $fs->createDirectory(IPE_TEST_TMPDIR);

    // Let's start a fake webserver and a fake proxy server
    $serverProcesses = [];

    $webserverPort = null;
    $serverProcesses[] = Support\PHPServer::start(
        static function () use (&$webserverPort) {
            $webserverPort = Support\PHPServer::findFreeLocalPort();

            return "-S 0.0.0.0:{$webserverPort}";
        },
        IPE_TEST_TMPDIR . '/webserver.log',
        __DIR__ . '/assets/webserver',
        3
    );
    define('IPE_TEST_WEBSERVER_PORT', $webserverPort);

    $proxyserverPort = null;
    $serverProcesses[] = Support\PHPServer::start(
        static function () use (&$proxyserverPort) {
            $proxyserverPort = Support\PHPServer::findFreeLocalPort();

            return "proxy.php {$proxyserverPort}";
        },
        IPE_TEST_TMPDIR . '/proxy-launcher.log',
        __DIR__ . '/assets/webserver',
        3
    );
    define('IPE_TEST_PROXYSERVER_PORT', $proxyserverPort);

    register_shutdown_function(static function () use ($serverProcesses) {
        foreach (array_reverse($serverProcesses) as $process) {
            Support\PHPServer::stop($process);
        }
    });
};

$bootstrap();
unset($bootstrap);
