<?php

namespace IPE\Extension;

use IPE\Extension;
use IPE\Option\Values;
use IPE\OS;
use IPE\SystemDependencies\msodbcsql;

/**
 * What sqlsrv and pdo_sqlsrv have in common: they are released together.
 */
abstract class MicrosoftSQLServer extends Extension implements
    AvailableOnPECL,
    DeclaresDefaultVersion,
    DeclaresUnsupportedEnvironments,
    RequiresSystemDependencies
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresUnsupportedEnvironments::getUnsupportedEnvironments()
     */
    public static function getUnsupportedEnvironments()
    {
        return [
            'alpine3.7..3.8' => 'the Microsoft ODBC Driver requires unixODBC 2.3.6 or later',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return [static::getHandle()];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return static::getHandle();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Database';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Values $options)
    {
        return [msodbcsql::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     * @see https://learn.microsoft.com/en-us/sql/connect/php/system-requirements-for-the-php-sql-driver
     */
    public function getDefaultVersionRequirements()
    {
        $phpVersion = $this->php->getMajorMinorVersion();
        // Before 5.8, sqlsrv asks for the "C.UTF-8;C;C;C;C;C" locale, which libstdc++ rejects on Alpine
        if ($this->os instanceof OS\Alpine && $phpVersion <= 701) {
            return '5.8.1';
        }
        foreach ([
            700 => '5.3.0',
            701 => '5.6.1',
            702 => '5.8.1',
            703 => '5.9.0',
            704 => '5.10.1',
            800 => '5.11.1',
            802 => '5.12.0',
        ] as $maxPHPVersion => $version) {
            if ($phpVersion <= $maxPHPVersion) {
                return $version;
            }
        }

        return '';
    }
}
