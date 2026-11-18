<?php

namespace IPE;

/**
 * The architecture of a machine or of the binaries.
 *
 * @see \IPE\OS::getArchitecture()
 * @see \IPE\PHPInfo::getArchitecture()
 */
final class Architecture
{
    /**
     * @public
     * @var string
     */
    const X86 = 'x86';

    /**
     * @public
     * @var string
     */
    const X64 = 'x64';

    /**
     * @public
     * @var string
     */
    const ARM32 = 'arm32';

    /**
     * @public
     * @var string
     */
    const ARM64 = 'arm64';

    /**
     * @var self::*|''
     */
    private $name;

    /**
     * @var non-empty-string
     */
    private $rawName;

    /**
     * @param non-empty-string $rawName
     *
     * @example new Architecture('x86_64') has \IPE\Architecture::X64 as name
     * @example new Architecture('AMD64') has \IPE\Architecture::X64 as name
     */
    public function __construct($rawName)
    {
        if (preg_match('/^(i[3-6]86|x86)$/Di', $rawName)) {
            $this->name = self::X86;
        } elseif (preg_match('/^(x86_64|amd64|x64)$/Di', $rawName)) {
            $this->name = self::X64;
        } elseif (preg_match('/^(aarch64|arm64|armv8)$/Di', $rawName)) {
            $this->name = self::ARM64;
        } elseif (preg_match('/^arm(32|v[5-8].*)?$/Di', $rawName)) {
            // armv8l is an ARMv8 kernel running in 32-bit mode
            $this->name = self::ARM32;
        } else {
            $this->name = '';
        }
        $this->rawName = $rawName;
    }

    /**
     * @return self::*|'' one of the \IPE\Architecture constants, or an empty string if the architecture is not recognized
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return non-empty-string
     *
     * @example 'x86_64'
     * @example 'aarch64'
     * @example 'AMD64'
     */
    public function getRawName()
    {
        return $this->rawName;
    }

    /**
     * Get the value associated to this architecture.
     *
     * @template T
     *
     * @param array<self::*, T> $values the keys are the \IPE\Architecture constants
     * @param bool $fallbackToRawName return the raw name if $values doesn't contain this architecture (useful only for string values)
     *
     * @return ($fallbackToRawName is true ? T|non-empty-string : T|null) NULL if $values doesn't contain this architecture and $fallbackToRawName is false
     */
    public function map(array $values, $fallbackToRawName = false)
    {
        if (array_key_exists($this->name, $values)) {
            return $values[$this->name];
        }

        return $fallbackToRawName ? $this->rawName : null;
    }
}
