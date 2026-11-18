<?php

namespace IPE\OS;

use IPE\OS;
use IPE\PHPInfo;

/**
 * A 32-bit PHP can run on a 64-bit machine, and it can only load 32-bit libraries.
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
     * @public
     * @var string
     */
    const OTHER = 'other';

    /**
     * @param string $name
     *
     * @return non-empty-string one of the \IPE\OS\Architecture constants
     *
     * @example fromName('x86_64') returns \IPE\OS\Architecture::X64
     * @example fromName('AMD64') returns \IPE\OS\Architecture::X64
     */
    public static function fromName($name)
    {
        if (preg_match('/^(i[3-6]86|x86)$/Di', $name)) {
            return self::X86;
        }
        if (preg_match('/^(x86_64|amd64|x64)$/Di', $name)) {
            return self::X64;
        }
        if (preg_match('/^(aarch64|arm64|armv8)$/Di', $name)) {
            return self::ARM64;
        }

        // armv8l is an ARMv8 kernel running in 32-bit mode
        return preg_match('/^arm(v[5-8].*)?$/Di', $name) ? self::ARM32 : self::OTHER;
    }

    /**
     * Get the architecture that the binaries loaded by PHP must have.
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string one of the \IPE\OS\Architecture constants
     */
    public static function ofPHP(PHPInfo $php, OS $os)
    {
        // For example, Windows on ARM64 runs x64 builds of PHP
        $architecture = self::fromName($php->getBuildArchitecture());
        if ($architecture !== self::OTHER) {
            return $architecture;
        }
        $architecture = $os->getMachineArchitecture();
        if ($php->getBits() === 32) {
            if ($architecture === self::X64) {
                return self::X86;
            }
            if ($architecture === self::ARM64) {
                return self::ARM32;
            }
        }

        return $architecture;
    }

    /**
     * Get the name used by a vendor for the architecture of PHP.
     * We don't refuse unknown architectures, since a vendor may support them in the future: downloading the file will tell us.
     *
     * @param array<string, non-empty-string> $names the keys are the \IPE\OS\Architecture constants
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    public static function getVendorName(PHPInfo $php, OS $os, array $names)
    {
        $architecture = self::ofPHP($php, $os);
        if (isset($names[$architecture])) {
            return $names[$architecture];
        }
        switch ($architecture) {
            // The operating system may report the 64-bit name of the machine
            case self::X86:
                return 'i386';
            case self::ARM32:
                return 'armv7l';
        }
        if ($architecture !== $os->getMachineArchitecture()) {
            switch ($architecture) {
                case self::X64:
                    return 'x86_64';
                case self::ARM64:
                    return 'aarch64';
            }
        }

        return $os->getRawMachineArchitecture();
    }

    /**
     * Describe the architecture to the user.
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     *
     * @example 'x86_64'
     * @example '32-bit PHP on x86_64'
     * @example 'x64 PHP on ARM64'
     */
    public static function describe(PHPInfo $php, OS $os)
    {
        $raw = $os->getRawMachineArchitecture();
        $buildArchitecture = $php->getBuildArchitecture();
        $architecture = self::fromName($buildArchitecture);
        if ($architecture !== self::OTHER && $architecture !== $os->getMachineArchitecture()) {
            return "{$buildArchitecture} PHP on {$raw}";
        }

        return ($php->getBits() === 32 ? '32-bit PHP on ' : '') . $raw;
    }
}
