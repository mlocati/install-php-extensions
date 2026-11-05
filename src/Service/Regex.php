<?php

namespace IPE\Service;

use IPE\Exception;

/**
 * Wrappers around the preg_* functions that throw an exception instead of returning null/false on failure.
 */
final class Regex
{
    /**
     * @param non-empty-string $pattern
     * @param string $replacement
     * @param string $subject
     * @param int $limit
     *
     * @throws \IPE\Exception\Regex if the regular expression fails
     *
     * @return string
     */
    public static function replace($pattern, $replacement, $subject, $limit = -1)
    {
        $result = preg_replace($pattern, $replacement, $subject, $limit);
        if ($result === null) {
            throw self::buildException('preg_replace');
        }

        return $result;
    }

    /**
     * @param non-empty-string $pattern
     * @param callable(array<array-key, string>): string $callback
     * @param string $subject
     * @param int $limit
     *
     * @throws \IPE\Exception\Regex if the regular expression fails
     *
     * @return string
     */
    public static function replaceCallback($pattern, $callback, $subject, $limit = -1)
    {
        $result = preg_replace_callback($pattern, $callback, $subject, $limit);
        if ($result === null) {
            throw self::buildException('preg_replace_callback');
        }

        return $result;
    }

    /**
     * @param non-empty-string $pattern
     * @param string $subject
     * @param int $limit
     * @param bool $noEmpty set to true to skip the empty chunks (PREG_SPLIT_NO_EMPTY)
     *
     * @throws \IPE\Exception\Regex if the regular expression fails
     *
     * @return ($noEmpty is true ? list<non-empty-string> : list<string>)
     */
    public static function split($pattern, $subject, $limit = -1, $noEmpty = false)
    {
        $result = preg_split($pattern, $subject, $limit, $noEmpty ? PREG_SPLIT_NO_EMPTY : 0);
        // Before PHP 7.4 preg_split() may fail without returning false
        if ($result === false || preg_last_error() !== PREG_NO_ERROR) {
            throw self::buildException('preg_split');
        }

        return $result;
    }

    /**
     * @param string $function
     *
     * @return \IPE\Exception\Regex
     */
    private static function buildException($function)
    {
        // preg_last_error_msg() is available since PHP 8.0
        $details = function_exists('preg_last_error_msg') ? preg_last_error_msg() : 'error code ' . preg_last_error();

        return new Exception\Regex("{$function} failed: {$details}");
    }
}
