<?php

// @php-cs-fixer-ignore psr_autoloading

class TestFailedException extends RuntimeException
{
}

error_reporting(-1);

set_error_handler(
    static function ($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    },
    -1
);

set_exception_handler(
    static function ($exception) {
        echo $exception instanceof TestFailedException ? '' : (get_class($exception) . ': '), $exception->getMessage(), "\nFile: ", $exception->getFile(), "\nLine: ", $exception->getLine(), "\n";
        exit(1);
    }
);

// Fatal errors can't be caught: at least write them to stdout (before PHP 8 the exit code stays 255, ignoring exit(1))
ini_set('display_errors', '0');
register_shutdown_function(
    static function () {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            echo "Fatal error: {$error['message']}\n";
            exit(1);
        }
    }
);

/**
 * Get the value of an option passed to install-php-extensions (for example 'saxon:edition').
 *
 * @param string $name
 *
 * @return string|null NULL if the option has not been specified
 */
function getInstallOption($name)
{
    $options = json_decode((string) getenv('TEST_IPE_OPTIONS'), true);
    foreach (is_array($options) ? $options : [] as $option) {
        $chunks = explode('=', (string) $option, 2);
        if ($chunks[0] === "--{$name}") {
            return isset($chunks[1]) ? $chunks[1] : '';
        }
    }

    return null;
}
