<?php

namespace IPE\Dev;

use IPE\Extension;
use IPE\PHPInfo;
use IPE\SystemPackager;

/**
 * Create extensions without their services, to inspect what depends only on the PHP version (like supportsZTS() and supportsNTS()) or on the system packager.
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
     * @param \IPE\OS|null $os
     *
     * @return T
     */
    public static function createForPHPVersion($class, $phpVersion, $os = null)
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
        if ($os !== null) {
            self::setProperty(new \ReflectionProperty(Extension::class, 'os'), $extension, $os);
        }

        return $extension;
    }

    /**
     * Create an operating system that only provides its system packager (to inspect the names of the system packages).
     *
     * @param class-string<\IPE\OS\Debian>|class-string<\IPE\OS\Alpine>|class-string<\IPE\OS\Posix\RHELLike> $osClass
     * @param class-string<\IPE\SystemPackager> $packagerClass
     *
     * @throws \RuntimeException
     *
     * @return \IPE\OS\Debian|\IPE\OS\Alpine|\IPE\OS\Posix\RHELLike
     */
    public static function createOSWithSystemPackager($osClass, $packagerClass)
    {
        $osReflection = new \ReflectionClass($osClass);
        $os = $osReflection->newInstanceWithoutConstructor();
        $packager = (new \ReflectionClass($packagerClass))->newInstanceWithoutConstructor();
        self::setProperty(new \ReflectionProperty(SystemPackager::class, 'os'), $packager, $os);
        // The property is private in the parent class of the RHEL-like systems
        $declaringClass = $osReflection;
        while (!$declaringClass->hasProperty('systemPackager')) {
            $declaringClass = $declaringClass->getParentClass();
            if ($declaringClass === false) {
                throw new \RuntimeException("{$osClass} doesn't have a system packager");
            }
        }
        self::setProperty($declaringClass->getProperty('systemPackager'), $os, $packager);

        return $os;
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
