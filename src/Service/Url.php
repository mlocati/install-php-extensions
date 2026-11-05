<?php

namespace IPE\Service;

use IPE\Exception;

final class Url
{
    /**
     * @param string $url
     * @param string $argumentName used in the error message
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return non-empty-string
     */
    public static function validate($url, $argumentName = '$url')
    {
        $flags = FILTER_FLAG_PATH_REQUIRED;
        if (PHP_VERSION_ID < 70300) {
            $flags |= FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED;
        }
        $validUrl = filter_var($url, FILTER_VALIDATE_URL, $flags);
        if (!$validUrl) {
            throw new Exception\InvalidArgument("Invalid argument {$argumentName}: '{$url}' is not a valid URL");
        }

        return $validUrl;
    }
}
