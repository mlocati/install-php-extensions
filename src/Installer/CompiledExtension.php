<?php

namespace IPE\Installer;

use IPE\Extension\Resolved;

/**
 * A PHP extension that has been compiled and copied to the PHP extension directory, but not enabled yet.
 */
final class CompiledExtension
{
    /**
     * @var \IPE\Extension\Resolved
     */
    private $resolved;

    /**
     * @var non-empty-string
     */
    private $moduleName;

    /**
     * @var non-empty-string
     */
    private $modulePath;

    /**
     * @var bool
     */
    private $zendExtension;

    /**
     * @var string
     */
    private $iniSettings;

    /**
     * @var list<string>
     */
    private $configureArguments;

    /**
     * @param non-empty-string $moduleName the name of the module file, without the extension (eg 'apcu' for 'apcu.so')
     * @param non-empty-string $modulePath the full path of the module file in the PHP extension directory
     * @param bool $zendExtension
     * @param string $iniSettings additional settings for the ini file that enables the module
     * @param list<string> $configureArguments the arguments passed to configure (empty if the module has not been compiled with configure)
     */
    public function __construct(Resolved $resolved, $moduleName, $modulePath, $zendExtension, $iniSettings = '', array $configureArguments = [])
    {
        $this->resolved = $resolved;
        $this->moduleName = $moduleName;
        $this->modulePath = $modulePath;
        $this->zendExtension = $zendExtension;
        $this->iniSettings = $iniSettings;
        $this->configureArguments = $configureArguments;
    }

    /**
     * @return \IPE\Extension\Resolved
     */
    public function getResolved()
    {
        return $this->resolved;
    }

    /**
     * @return non-empty-string
     *
     * @example 'apcu'
     */
    public function getModuleName()
    {
        return $this->moduleName;
    }

    /**
     * @return non-empty-string
     *
     * @example '/usr/local/lib/php/extensions/no-debug-non-zts-20240924/apcu.so'
     */
    public function getModulePath()
    {
        return $this->modulePath;
    }

    /**
     * Must the module be loaded with zend_extension instead of extension?
     *
     * @return bool
     */
    public function isZendExtension()
    {
        return $this->zendExtension;
    }

    /**
     * Get the additional settings for the ini file that enables the module.
     *
     * @return string
     */
    public function getIniSettings()
    {
        return $this->iniSettings;
    }

    /**
     * Get the arguments passed to configure (empty if the module has not been compiled with configure).
     *
     * @return list<string>
     */
    public function getConfigureArguments()
    {
        return $this->configureArguments;
    }
}
