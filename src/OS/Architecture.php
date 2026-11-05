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
     * Get the architecture that the binaries loaded by PHP must have.
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string one of the \IPE\OS\Architecture constants
     */
    public static function ofPHP(PHPInfo $php, OS $os)
    {
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
     */
    public static function describe(PHPInfo $php, OS $os)
    {
        return ($php->getBits() === 32 ? '32-bit PHP on ' : '') . $os->getRawMachineArchitecture();
    }
}
