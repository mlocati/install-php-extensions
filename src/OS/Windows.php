<?php

namespace IPE\OS;

use IPE\Exception;
use IPE\Filesystem;
use IPE\OS;
use IPE\Traits;

/**
 * @see https://learn.microsoft.com/en-us/windows/win32/api/winnt/ns-winnt-osversioninfoexa#remarks
 */
class Windows implements OS
{
    use Traits\MachineArchitecture;

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var non-empty-string
     * @example 'Microsoft Windows 11 Pro'
     */
    private $caption;

    /**
     * @var positive-int
     * @example 64
     */
    private $bits;

    /**
     * @var non-empty-string
     * @example '10.0.26200'
     */
    private $version;

    /**
     * @param non-empty-string $caption eg 'Microsoft Windows 11 Pro'
     * @param positive-int $bits eg 64
     * @param non-empty-string $version eg '10.0.26200'
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    public function __construct($caption, $bits, $version, Filesystem $fs)
    {
        $this->fs = $fs;
        $this->caption = $caption;
        if (($bits % 8) !== 0) {
            throw new Exception\InvalidArgument('Invalid $bits');
        }
        $this->bits = $bits;
        if (!preg_match('#^[1-9][0-9]?\.(0|[1-9][0-9]?)\.(0|[1-9][0-9]{0,4})(\.\d+)*$#D', $version)) {
            throw new Exception\InvalidArgument('Invalid $version');
        }

        $this->version = $version;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'windows';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::parseNumericVersion()
     *
     * @example 100026200 for '10.0.26200'
     */
    public static function parseNumericVersion($version)
    {
        if (!preg_match('#^[1-9][0-9]?\.(0|[1-9][0-9]?)\.(0|[1-9][0-9]{0,4})$#D', $version)) {
            return null;
        }
        list($dwMajorVersion, $dwMinorVersion, $dwBuildNumber) = array_map('intval', explode('.', $version));

        return max(1, ($dwMajorVersion * 100 + $dwMinorVersion) * 100000 + $dwBuildNumber);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getFilesystem()
     */
    public function getFilesystem()
    {
        return $this->fs;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getDisplayName()
     *
     * @example 'Microsoft Windows 11 Pro [10.0.26200]'
     */
    public function getDisplayName()
    {
        return "{$this->caption} [{$this->version}]";
    }

    /**
     * @return string
     *
     * @example '10.0.26200'
     *
     * @see https://learn.microsoft.com/en-us/windows/win32/api/winnt/ns-winnt-osversioninfoexa#remarks
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return positive-int
     *
     * @example 64
     */
    public function getBits()
    {
        return $this->bits;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getNumericVersion()
     *
     * @example 100026200
     *              ^^^^^ build
     *            ^^ minor
     *          ^^ maior
     * @see https://learn.microsoft.com/en-us/windows/win32/api/winnt/ns-winnt-osversioninfoexa#remarks
     */
    public function getNumericVersion()
    {
        list($dwMajorVersion, $dwMinorVersion, $dwBuildNumber) = array_map('intval', explode('.', $this->version, 4));

        return max(1, ($dwMajorVersion * 100 + $dwMinorVersion) * 100000 + $dwBuildNumber);
    }

    /**
     * @return string empty string if it can't be detected
     *
     * @see https://learn.microsoft.com/en-us/windows/win32/winprog64/wow64-implementation-details#environment-variables
     */
    protected function detectMachineArchitecture()
    {
        // A 32-bit process on a 64-bit system sees x86 in PROCESSOR_ARCHITECTURE
        foreach (['PROCESSOR_ARCHITEW6432', 'PROCESSOR_ARCHITECTURE'] as $name) {
            $value = getenv($name);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }
}
