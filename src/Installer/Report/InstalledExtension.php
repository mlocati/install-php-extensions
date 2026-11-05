<?php

namespace IPE\Installer\Report;

use IPE\Extension\Resolved;

/**
 * A PHP extension that has been installed.
 */
final class InstalledExtension implements \JsonSerializable
{
    /**
     * Compiled from its source code with phpize, configure and make.
     *
     * @public
     * @var string
     */
    const METHOD_COMPILED = 'compiled';

    /**
     * Module provided (or built) by its vendor, and enabled by IPE.
     *
     * @public
     * @var string
     */
    const METHOD_BINARY = 'binary';

    /**
     * Installed and enabled by the installer of its vendor.
     *
     * @public
     * @var string
     */
    const METHOD_VENDOR_INSTALLER = 'vendor-installer';

    /**
     * @var \IPE\Extension\Resolved
     */
    private $resolved;

    /**
     * @var non-empty-string
     */
    private $method;

    /**
     * @var list<non-empty-string>
     */
    private $moduleFiles;

    /**
     * @var bool|null
     */
    private $zendExtension;

    /**
     * @var array<non-empty-string, bool>
     */
    private $iniFiles;

    /**
     * @var list<non-empty-string>
     */
    private $loadedModules;

    /**
     * @var list<string>
     */
    private $configureArguments;

    /**
     * @var float
     */
    private $duration;

    /**
     * @var string
     */
    private $enableScript = '';

    /**
     * @param non-empty-string $method one of the METHOD_* constants
     * @param list<non-empty-string> $moduleFiles the full paths of the module files (.so)
     * @param bool|null $zendExtension NULL if unknown
     * @param array<non-empty-string, bool> $iniFiles the keys are the full paths of the ini files, the values tell if they are active
     * @param list<non-empty-string> $loadedModules the names of the modules, as seen by PHP
     * @param list<string> $configureArguments the arguments passed to configure (empty if the extension has not been compiled with configure)
     * @param float $duration the seconds spent to install the extension
     */
    public function __construct(Resolved $resolved, $method, array $moduleFiles, $zendExtension, array $iniFiles, array $loadedModules, array $configureArguments, $duration)
    {
        $this->resolved = $resolved;
        $this->method = $method;
        $this->moduleFiles = $moduleFiles;
        $this->zendExtension = $zendExtension;
        $this->iniFiles = $iniFiles;
        $this->loadedModules = $loadedModules;
        $this->configureArguments = $configureArguments;
        $this->duration = $duration;
    }

    /**
     * Create a copy of this instance, with other ini files.
     *
     * @param array<non-empty-string, bool> $iniFiles the keys are the full paths of the ini files, the values tell if they are active
     *
     * @return self
     */
    public function withIniFiles(array $iniFiles)
    {
        $result = clone $this;
        $result->iniFiles = $iniFiles;

        return $result;
    }

    /**
     * Create a copy of this instance, with the script that enables the extension.
     *
     * @param string $enableScript
     *
     * @return self
     */
    public function withEnableScript($enableScript)
    {
        $result = clone $this;
        $result->enableScript = $enableScript;

        return $result;
    }

    /**
     * @return \IPE\Extension\Resolved
     */
    public function getResolved()
    {
        return $this->resolved;
    }

    /**
     * @return non-empty-string one of the METHOD_* constants
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Get the full paths of the module files.
     *
     * @return list<non-empty-string>
     *
     * @example ['/usr/local/lib/php/extensions/no-debug-non-zts-20240924/apcu.so']
     */
    public function getModuleFiles()
    {
        return $this->moduleFiles;
    }

    /**
     * Must the module be loaded with zend_extension instead of extension?
     *
     * @return bool|null NULL if unknown
     */
    public function isZendExtension()
    {
        return $this->zendExtension;
    }

    /**
     * Get the ini files that load the modules.
     *
     * @return array<non-empty-string, bool> the keys are the full paths of the ini files, the values tell if they are active (false if the extension= line is commented out)
     */
    public function getIniFiles()
    {
        return $this->iniFiles;
    }

    /**
     * Get the names of the modules, as seen by PHP.
     *
     * @return list<non-empty-string>
     *
     * @example ['Zend OPcache']
     */
    public function getLoadedModules()
    {
        return $this->loadedModules;
    }

    /**
     * Get the arguments passed to configure (empty if the extension has not been compiled with configure).
     *
     * @return list<string>
     */
    public function getConfigureArguments()
    {
        return $this->configureArguments;
    }

    /**
     * Get the seconds spent to install the extension.
     *
     * @return float
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * Get the full path of the script that enables the extension (empty string if not created).
     *
     * @return string
     */
    public function getEnableScript()
    {
        return $this->enableScript;
    }

    /**
     * {@inheritdoc}
     *
     * @see \JsonSerializable::jsonSerialize()
     *
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange()]
    public function jsonSerialize()
    {
        $iniFiles = [];
        foreach ($this->iniFiles as $path => $active) {
            $iniFiles[] = ['path' => $path, 'active' => $active];
        }
        $options = $this->resolved->getOptionValues()->getCustomValues();

        return [
            'name' => $this->resolved->getName(),
            'version' => $this->resolved->getVersion(),
            'origin' => $this->resolved->getOrigin(),
            'asked' => $this->resolved->isAsked(),
            'requiredBy' => $this->resolved->getRequiredBy(),
            'method' => $this->method,
            'options' => $options === [] ? (object) [] : $options,
            'configureArguments' => $this->configureArguments,
            'moduleFiles' => $this->moduleFiles,
            'zendExtension' => $this->zendExtension,
            'iniFiles' => $iniFiles,
            'loadedModules' => $this->loadedModules,
            'enableScript' => $this->enableScript,
            'duration' => round($this->duration, 3),
        ];
    }
}
