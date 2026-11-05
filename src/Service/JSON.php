<?php

namespace IPE\Service;

use IPE\Exception;

/**
 * Wrappers around the json_* functions that throw the same exception with every PHP version.
 */
final class JSON
{
    /**
     * @param mixed $value
     * @param bool $prettyPrint
     *
     * @throws \IPE\Exception\JSON if the value can't be serialized
     *
     * @return string
     */
    public static function encode($value, $prettyPrint = false)
    {
        $flags = JSON_UNESCAPED_SLASHES | ($prettyPrint ? JSON_PRETTY_PRINT : 0);
        if (PHP_VERSION_ID >= 70300) {
            try {
                return json_encode($value, $flags | JSON_THROW_ON_ERROR);
            } catch (\JsonException $x) {
                throw new Exception\JSON("json_encode failed: {$x->getMessage()}");
            }
        }
        $result = json_encode($value, $flags);
        if ($result === false) {
            throw new Exception\JSON('json_encode failed: ' . json_last_error_msg());
        }

        return $result;
    }

    /**
     * @param string $json
     * @param bool $associative set to true to decode the JSON objects as associative arrays instead of \stdClass instances
     *
     * @throws \IPE\Exception\JSON if $json is not valid
     *
     * @return mixed
     */
    public static function decode($json, $associative = false)
    {
        if (PHP_VERSION_ID >= 70300) {
            try {
                return json_decode($json, $associative, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $x) {
                throw new Exception\JSON("json_decode failed: {$x->getMessage()}");
            }
        }
        // PHP 5 accepts empty strings
        if ($json === '') {
            throw new Exception\JSON('json_decode failed: Syntax error');
        }
        $result = json_decode($json, $associative);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception\JSON('json_decode failed: ' . json_last_error_msg());
        }

        return $result;
    }
}
