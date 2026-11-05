<?php

class proxy
{
    const CLIENT_TIMEOUT = 5; // 5 seconds

    /**
     * @var int
     */
    private $port;

    /**
     * @var resource|null
     */
    private $logFile = null;

    /**
     * @var resource|null
     */
    private $server = null;

    /**
     * @var float|null
     */
    private $startedAt = null;

    /**
     * @param int|mixed $port
     */
    public function __construct($port)
    {
        $port = is_numeric($port) ? (int) $port : 0;
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException("Please specify the TCP port\n");
        }
        $logFile = fopen(__DIR__ . '/../../tmp/proxy.log', 'w');
        if (!$logFile) {
            throw new RuntimeException('Failed to create the log file');
        }
        $this->logFile = $logFile;
        try {
            $this->log("Starting server listening at port {$port}");
            $errorCode = -1;
            $errorMessage = '';
            $server = stream_socket_server("tcp://0.0.0.0:{$port}", $errorCode, $errorMessage);
            if ($server === false) {
                throw new RuntimeException("stream_socket_server() failed with error code {$errorCode}: {$errorMessage}");
            }
            $this->server = $server;
        } catch (Exception $x) {
            $this->log(rtrim($x->getMessage()));
            @fclose($this->logFile);
            throw $x;
        }
    }

    public function __destruct()
    {
        $this->log('Closing server');
        @fclose($this->server);
        $this->server = null;
        $this->log('Closing log');
        @fclose($this->logFile);
        $this->logFile = null;
    }

    public function run()
    {
        $this->log('Started at ' . gmdate('c'));
        $this->startedAt = microtime(true);
        while (true) {
            $client = null;
            try {
                $this->log('Waiting for a connection');
                $client = stream_socket_accept($this->server, -1);
                if ($client === false) {
                    throw new RuntimeException('stream_socket_accept() failed');
                }
                $this->log('Incoming connection detected');
                $this->handleClient($client);
                fflush($client);
            } catch (Exception $x) {
                $this->log(rtrim((string) $x));
                throw $x;
            } finally {
                if ($client) {
                    @fclose($client);
                }
            }
        }
    }

    /**
     * @param resource $client
     */
    private function handleClient($client)
    {
        stream_set_timeout($client, 0, 600000); // 600ms
        $request = $this->readRequest($client);
        if ($request === null) {
            $this->log('No data received');
            fwrite($client, "HTTP/1.1 400 Bad Request\r\n\r\nNo data received");

            return;
        }
        list($requestLine, $requestHeaders, $requestBody) = $request;
        // Not json_encode(): before PHP 8.0 json may be a shared extension, not loaded with php -n
        $serializedRequestHeaders = var_export($requestHeaders, true);
        $this->log(
            <<<EOT
Data received:
Request line: {$requestLine}
Headers: {$serializedRequestHeaders}
Body: {$requestBody}
EOT
        );
        $matches = null;
        if (!preg_match('#^(?<method>[A-Z]+)\s+(?<uri>\S+)\s+HTTP/(?<httpVersion>\d\.\d)#i', $requestLine, $matches)) {
            $this->log('Unable to recognize the request line');
            fwrite($client, "HTTP/1.1 400 Bad Request\r\n\r\nMalformed request line");

            return;
        }
        $requestMethod = strtoupper($matches['method']);
        $requestUrl = $matches['uri'];
        $requestHttpVersion = $matches['httpVersion'];
        $this->log(
            <<<EOT
Parsed request line:
Method: {$requestMethod}
URL: {$requestUrl}
Version: {$requestHttpVersion}
EOT
        );
        switch ($requestMethod) {
            case 'CONNECT':
                $this->handleConnect($client);
                break;
            case 'GET':
                $this->handleGet($client, $requestUrl, $requestHeaders);
                break;
            default:
                $this->log('Unrecognized method');
                fwrite($client, "HTTP/1.1 400 Bad Request\r\n\r\nMalformed request line");
                break;
        }
    }

    /**
     * @param resource $client
     *
     * @return array{string, array<string,string>, string}|null
     */
    private function readRequest($client)
    {
        $this->log('Start reading request');
        $requestLine = fgets($client);
        $requestLine = is_string($requestLine) ? trim($requestLine) : '';
        if ($requestLine === '') {
            $this->log('Empty header request');

            return null;
        }
        $isExpectingBody = !preg_match('#^(CONNECT|GET)\s#i', $requestLine);
        $headers = [];
        $body = null;
        while (($line = fgets($client)) !== false) {
            if ($body !== null) {
                $body .= $line;
                continue;
            }
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                $body = '';
                if (!$isExpectingBody) {
                    break;
                }
                continue;
            }
            list($headerName, $headerValue) = explode(':', $line, 2) + [1 => ''];
            $headers[strtolower(trim($headerName))] = trim($headerValue);
        }
        if ($body === null) {
            $body = '';
        }

        return [$requestLine, $headers, $body];
    }

    /**
     * @param resource $client
     */
    private function handleConnect($client)
    {
        $this->log('CONNECT start');
        fwrite($client, "HTTP/1.1 200 Connection established\r\n\r\n");
        // non-blocking read + discard for up to 30s of inactivity
        stream_set_blocking($client, false);
        $lastActivity = time();
        $timeout = 30;
        while (!feof($client)) {
            $data = @fread($client, 8192);
            if ($data === false || $data === '') {
                usleep(100000); // 100ms
            } else {
                // simply discard data (could log if useful)
                $lastActivity = time();
                $this->log("Data received after CONNECT:\n{$data}");
            }
            $elapsed = time() - $lastActivity;
            if ($elapsed > $timeout) {
                break;
            }
        }
        $this->log('CONNECT done');
    }

    /**
     * @param resource $client
     * @param string $requestUrl
     * @param array<string,string> $requestHeaders
     */
    private function handleGet($client, $requestUrl, array $requestHeaders)
    {
        $matches = null;
        switch ($requestUrl) {
            case 'http://127.0.0.1:123/unprotected':
                $responseHeaders = [
                    'HTTP/1.1 200 OK',
                    'Content-Type: text/plain',
                    'Connection: close',
                ];
                $responseBody = 'Content of unprotected';
                break;
            case 'http://127.0.0.1:123/protected':
                $authorized = false;
                if (!isset($requestHeaders['proxy-authorization'])) {
                    $this->log('Sending 407 response because the Proxy-Authorization header is missing');
                } elseif (!preg_match('#^basic\s+(?<hash>\S+)#i', $requestHeaders['proxy-authorization'], $matches)) {
                    $this->log("Sending 407 because the Proxy-Authorization header ({$requestHeaders['proxy-authorization']}) is malformed");
                } else {
                    $expectedHash = base64_encode('ipe:pei');
                    if ($matches['hash'] !== $expectedHash) {
                        $this->log("Sending 407 because the Proxy-Authorization header contains the hash '{$matches['hash']}' whereas we expect '{$expectedHash}'");
                    } else {
                        $this->log('The Proxy-Authorization header contains the expected hash');
                        $authorized = true;
                    }
                }
                if ($authorized) {
                    $responseHeaders = [
                        'HTTP/1.1 200 OK',
                        'Content-Type: text/plain',
                        'Connection: close',
                    ];
                    $responseBody = 'Content of protected';
                } else {
                    $responseHeaders = [
                        'HTTP/1.1 407 Proxy Authentication Required',
                        'Proxy-Authenticate: Basic realm="IPE Testing Suite"',
                    ];
                    $responseBody = 'Missing/invalid authehtication';
                }
                break;
            default:
                $responseHeaders = [
                    'HTTP/1.1 404 Not Found',
                ];
                $responseBody = 'Unable to find the requested path';
                break;
        }
        $responseHeaders[] = 'Content-Length: ' . strlen($responseBody);
        $response = implode("\r\n", $responseHeaders) . "\r\n\r\n{$responseBody}";
        $this->log("Sending response:\n{$response}");
        fwrite($client, $response);
    }

    private function log($text)
    {
        if ($this->startedAt === null) {
            $elapsed = 0.0;
        } else {
            $elapsed = microtime(true) - $this->startedAt;
        }
        $prefix = str_pad(number_format($elapsed, 3), 5) . ' ';
        $nextPrefix = str_repeat(' ', strlen($prefix));
        foreach (explode("\n", str_replace("\r\n", "\n", $text)) as $line) {
            fwrite($this->logFile, $prefix . $line . "\n");
            $prefix = $nextPrefix;
        }
        fflush($this->logFile);
    }
}

try {
    $server = new proxy($argv[1] ?: null);
    set_time_limit(0);
    $server->run();
} catch (RuntimeException $x) {
    fwrite(STDERR, $x->getMessage() . "\n");
    exit(1);
}
