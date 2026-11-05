<?php

namespace IPE\Extension\Version;

final class Stability
{
    /**
     * @public
     * @var string
     */
    const DEV = 'dev';

    /**
     * @public
     * @var string
     */
    const ALPHA = 'alpha';

    /**
     * @public
     * @var string
     */
    const BETA = 'beta';

    /**
     * @public
     * @var string
     */
    const RC = 'RC';

    /**
     * @public
     * @var string
     */
    const STABLE = 'stable';

    /**
     * @return non-empty-list<non-empty-string>
     */
    public static function getAll()
    {
        /** @var non-empty-list<non-empty-string>|null $result */
        static $result;
        if ($result === null) {
            $result = [
                self::DEV,
                self::ALPHA,
                self::BETA,
                self::RC,
                self::STABLE,
            ];
        }

        return $result;
    }

    /**
     * @param non-empty-string $value
     *
     * @return bool
     */
    public static function isValid($value)
    {
        return in_array($value, self::getAll(), true);
    }

    /**
     * @param non-empty-string $value
     *
     * @return string one of the stability constants, or an empty string if $value is not valid
     */
    public static function normalize($value)
    {
        /** @var array<string, non-empty-string>|null $map */
        static $map;
        if ($map === null) {
            $all = self::getAll();
            $map = array_combine(array_map('strtolower', $all), $all);
        }
        $value = strtolower($value);

        return isset($map[$value]) ? $map[$value] : '';
    }

    /**
     * @param non-empty-string $a
     * @param non-empty-string $b
     *
     * @return int -1|0|1
     */
    public static function compare($a, $b)
    {
        $aValid = self::isValid($a);
        $bValid = self::isValid($b);
        if ($aValid !== $bValid) {
            return $aValid ? 1 : -1;
        }
        if (!$aValid) {
            return 0;
        }
        $all = self::getAll();
        $aIndex = array_search($a, $all, true);
        $bIndex = array_search($b, $all, true);

        return $aIndex < $bIndex ? -1 : ($aIndex > $bIndex ? 1 : 0);
    }
}
