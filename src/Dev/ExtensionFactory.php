<?php

namespace IPE\Dev;

use IPE\Extension;
use IPE\PHPInfo;

/**
 * Create extensions without their services, to inspect what depends only on the PHP version (like supportsZTS() and supportsNTS()).
 *
 * @internal
 */
final class ExtensionFactory
{
    /**
     * @template T of \IPE\Extension
     *
     * @param class-string<T> $class
     * @param string $phpVersion major.minor
     *
     * @return T
     */
    public static function createForPHPVersion($class, $phpVersion)
    {
        list($major, $minor) = array_map('intval', explode('.', $phpVersion));
        $phpInfoClass = new \ReflectionClass(PHPInfo::class);
        $php = $phpInfoClass->newInstanceWithoutConstructor();
        foreach ([
            'version' => "{$phpVersion}.0",
            'baseVersion' => "{$phpVersion}.0",
            'majorVersion' => $major,
            'majorMinorVersion' => $major * 100 + $minor,
        ] as $name => $value) {
            self::setProperty($phpInfoClass->getProperty($name), $php, $value);
        }
        $extensionClass = new \ReflectionClass($class);
        $extension = $extensionClass->newInstanceWithoutConstructor();
        self::setProperty(new \ReflectionProperty(Extension::class, 'php'), $extension, $php);

        return $extension;
    }

    /**
     * @param object $object
     * @param mixed $value
     *
     * @return void
     */
    private static function setProperty(\ReflectionProperty $property, $object, $value)
    {
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue($object, $value);
    }
}
