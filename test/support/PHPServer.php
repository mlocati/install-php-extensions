<?php

namespace IPE\Test\Support;

/**
 * Starts and stops the PHP processes (webserver, proxy) used by the tests.
 */
final class PHPServer
{
    /**
     * @var list<int>
     */
    private static $excludedPorts = [];

    /**
     * @throws \RuntimeException
     *
     * @return int
     */
    public static function findFreeLocalPort()
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            while (true) {
                $port = mt_rand(49152, 65535);
                if (!in_array($port, self::$excludedPorts, true)) {
                    break;
                }
            }
            self::$excludedPorts[] = $port;
            if (function_exists('socket_create_listen')) {
                $socket = @socket_create_listen($port, 5);
                if ($socket) {
                    socket_close($socket);

                    return $port;
                }
            } else {
                $errorCode = -1;
                $errorMessage = null;
                $socket = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.3);
                if (!$socket) {
                    return $port;
                }
                fclose($socket);
            }
        }
        throw new \RuntimeException('Failed to find an available TCP port');
    }

    /**
     * @param \Closure(): string $argsBuilder builds the arguments for the php command (called at every attempt)
     * @param string $logFile
     * @param string $cwd
     * @param int $retryAttempts
     *
     * @throws \RuntimeException
     *
     * @return resource
     */
    public static function start(\Closure $argsBuilder, $logFile, $cwd, $retryAttempts = 1)
    {
        $errors = [];
        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            $args = $argsBuilder();
            $pipes = null;
            $server = proc_open(
                escapeshellarg(PHP_BINARY) . ' -n ' . $args,
                [
                    0 => ['pipe', 'r'],
                    1 => ['file', $logFile, 'a'],
                    2 => ['file', $logFile, 'a'],
                ],
                $pipes,
                $cwd
            );
            if (!$server) {
                throw new \RuntimeException("Failed to start a fake PHP server ({$args}) - proc_open() failed");
            }
            fclose($pipes[0]);
            usleep(250000);
            $status = proc_get_status($server);
            if ($status['running']) {
                return $server;
            }
            proc_close($server);
            $error = "Failed to start a fake PHP server ({$args}) - start failed";
            if (is_readable($logFile)) {
                $loggedError = @file_get_contents($logFile);
                if ($loggedError) {
                    $error .= "\n{$loggedError}";
                    @file_put_contents($logFile, '');
                }
            }
            $errors[] = $error;
            if ($attempt < $retryAttempts) {
                usleep(250000 * $attempt);
            }
        }

        throw new \RuntimeException(implode("\n\n", $errors));
    }

    /**
     * @param resource $process
     *
     * @return void
     */
    public static function stop($process)
    {
        $status = proc_get_status($process);
        if ($status['running']) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // proc_terminate() doesn't seem to work on Windows
                exec("taskkill /PID {$status['pid']} /T /F 2>&1");
                usleep(10000);
                $status = proc_get_status($process);
            }
            if ($status['running']) {
                proc_terminate($process);
            }
        }
        proc_close($process);
    }
}
