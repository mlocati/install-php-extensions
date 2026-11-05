<?php

namespace IPE\Service;

/**
 * Formats byte counts for humans.
 */
final class SizeFormatter
{
    /**
     * @param int $bytes
     *
     * @return string
     *
     * @example '512 bytes'
     * @example '1.50 KB'
     * @example '12.34 MB'
     */
    public static function format($bytes)
    {
        if ($bytes < 0) {
            return '-' . self::format(-$bytes);
        }
        if ($bytes < 1024) {
            return $bytes === 1 ? '1 byte' : "{$bytes} bytes";
        }
        $value = $bytes / 1024;
        foreach (['KB', 'MB', 'GB'] as $unit) {
            if ($value < 1024) {
                return sprintf('%.2f %s', $value, $unit);
            }
            $value /= 1024;
        }

        return sprintf('%.2f TB', $value);
    }
}
