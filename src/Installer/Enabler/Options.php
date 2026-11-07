<?php

namespace IPE\Installer\Enabler;

use IPE\Exception;
use IPE\Installer\Enabler;

/**
 * How the installed extensions should be enabled.
 *
 * Instances are immutable: the with... methods return a modified copy.
 */
final class Options
{
    /**
     * @var bool
     */
    private $skipCheck = false;

    /**
     * @var bool
     */
    private $createEnableScripts = false;

    /**
     * @var non-empty-string
     */
    private $iniBasename = Enabler::INI_BASENAME_DEFAULT;

    /**
     * Should the extensions be enabled without checking that PHP loads them correctly?
     *
     * @return bool
     */
    public function shouldSkipCheck()
    {
        return $this->skipCheck;
    }

    /**
     * @param bool $value
     *
     * @return self
     */
    public function withSkipCheck($value)
    {
        $result = clone $this;
        $result->skipCheck = $value;

        return $result;
    }

    /**
     * Should the docker-php-ext-enable-<module> scripts be created when disabling the extensions?
     *
     * @return bool
     */
    public function shouldCreateEnableScripts()
    {
        return $this->createEnableScripts;
    }

    /**
     * @param bool $value
     *
     * @return self
     */
    public function withCreateEnableScripts($value)
    {
        $result = clone $this;
        $result->createEnableScripts = $value;

        return $result;
    }

    /**
     * @return non-empty-string Enabler::INI_BASENAME_COMPAT, or the name of the ini files (without .ini) with the {priority} and {name} placeholders
     */
    public function getIniBasename()
    {
        return $this->iniBasename;
    }

    /**
     * @param string $value Enabler::INI_BASENAME_COMPAT, or the name of the ini files (without .ini) with the {priority} and {name} placeholders
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return self
     */
    public function withIniBasename($value)
    {
        if ($value === '' || ($value !== Enabler::INI_BASENAME_COMPAT && !preg_match(Enabler::RX_VALID_INI_BASENAME, $value))) {
            throw new Exception\InvalidArgument("Invalid name of the ini files: '{$value}'");
        }
        $result = clone $this;
        $result->iniBasename = $value;

        return $result;
    }
}
