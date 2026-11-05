<?php

namespace IPE\Traits;

use IPE\Service\Regex;

trait WarningCatcher
{
    /**
     * Call a function collecting the PHP warnings/notices it raises instead of letting them be displayed.
     *
     * @template T
     *
     * @param \Closure(): T $callback
     * @param string $stripFunctionName the name of the function to be removed from the beginning of the error messages
     *
     * @return array{T, string} the result of $callback, and the error details (empty string if no error occurred)
     */
    protected static function wrap(\Closure $callback, $stripFunctionName = '')
    {
        $errorDetails = '';
        set_error_handler(
            static function ($errno, $errstr) use (&$errorDetails, $stripFunctionName) {
                $errstr = trim($errstr);
                if ($stripFunctionName !== '') {
                    $errstr = Regex::replace('/^' . preg_quote($stripFunctionName, '/') . '\\(.*?\\): \\s*(\\S)/', '\\1', $errstr);
                }
                if ($errorDetails === '') {
                    if ($errstr === '') {
                        $errstr = "Error {$errno}";
                    }
                    $errorDetails = $errstr;
                } elseif ($errstr !== '') {
                    $errorDetails .= "\n" . $errstr;
                }

                return true;
            },
            -1
        );
        try {
            $result = $callback();
        } finally {
            restore_error_handler();
        }

        return [$result, $errorDetails];
    }

    /**
     * @param string $main
     * @param string $errorDetails
     *
     * @return string
     */
    protected static function buildErrorMessage($main, $errorDetails)
    {
        return $errorDetails === '' ? $main : "{$main}:\n{$errorDetails}";
    }
}
