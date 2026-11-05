<?php

namespace IPE;

interface OS
{
    /**
     * Get the identifier of this OS, in lower case and without digits.
     *
     * @return non-empty-string
     *
     * @example 'alpine'
     */
    public static function getIdentifier();

    /**
     * Get the value that getNumericVersion() returns for a version.
     *
     * @param string $version
     *
     * @return positive-int|null NULL if $version is not valid
     *
     * @example 322 for '3.22' on Alpine
     * @example 13 for '13' on Debian
     * @example 2404 for '24.04' on Ubuntu
     */
    public static function parseNumericVersion($version);

    /**
     * @return \IPE\Filesystem
     */
    public function getFilesystem();

    /**
     * Get the display name of this OS.
     *
     * @example 'Alpine 3.22.4'
     * @example 'Debian 13 (Trixie)'
     * @example 'Ubuntu 24.04 (Noble Numbat)'
     *
     * @return non-empty-string
     */
    public function getDisplayName();

    /**
     * Get a comparable numeric version.
     *
     * @return positive-int
     *
     * @example 322 for Alpine 3.22.4
     * @example 13 for Debian 13 (Trixie)
     * @example 2404 for Ubuntu 24.04 (Noble Numbat)
     */
    public function getNumericVersion();

    /**
     * Get the hardware type of the machine (it may differ from the architecture of PHP: see PHPInfo::getBits()).
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string one of the \IPE\OS\Architecture constants
     */
    public function getMachineArchitecture();

    /**
     * Get the hardware type of the machine, as reported by the operating system.
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     *
     * @example 'x86_64'
     * @example 'aarch64'
     * @example 'AMD64'
     */
    public function getRawMachineArchitecture();
}
