<?php

namespace IPE\Installer\Enabler;

use IPE\Exception;
use IPE\Extension;
use IPE\Filesystem;
use IPE\Installer\CompiledExtension;
use IPE\Installer\Enabler;
use IPE\PHPInfo;

/**
 * Enables the compiled extensions of the PHP packages of Debian and Ubuntu, by creating their ini files in the mods-available directory and running phpenmod.
 */
final class WithModsAvailable extends Enabler
{
    /**
     * @var \IPE\Installer\Enabler\ModsAvailable
     */
    private $modsAvailable;

    /**
     * @throws \IPE\Exception\InvalidArgument if the options can't be used with the mods-available directory
     */
    public function __construct(PHPInfo $php, Filesystem $fs, \IPE\Logger $logger, ModsAvailable $modsAvailable, Options $options)
    {
        parent::__construct($php, $fs, $logger, $options);
        $iniBasename = $this->options->getIniBasename();
        if ($iniBasename !== Enabler::INI_BASENAME_DEFAULT) {
            throw new Exception\InvalidArgument("Invalid name of the ini files: '{$iniBasename}' (PHP uses the directory {$modsAvailable->getDirectory()}, where the names of the ini files are determined by phpenmod)");
        }
        if ($this->options->shouldCreateEnableScripts()) {
            throw new Exception\InvalidArgument("The scripts that enable the extensions can't be created when PHP uses the directory {$modsAvailable->getDirectory()}");
        }
        $this->modsAvailable = $modsAvailable;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Installer\Enabler::enable()
     */
    public function enable(CompiledExtension $compiled)
    {
        // Fail before checking the module
        $this->modsAvailable->checkIniFileWritable($compiled->getModuleName());

        return parent::enable($compiled);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Installer\Enabler::getIniFile()
     */
    public function getIniFile(CompiledExtension $compiled)
    {
        return $this->modsAvailable->getIniFile($compiled->getModuleName());
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Installer\Enabler::isIniFileActive()
     */
    public function isIniFileActive($iniFile)
    {
        $moduleName = $this->getModuleName($iniFile);
        if ($moduleName === '') {
            return parent::isIniFileActive($iniFile);
        }

        return $this->modsAvailable->isEnabled($moduleName);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Installer\Enabler::enableIniFile()
     */
    public function enableIniFile($iniFile)
    {
        $moduleName = $this->getModuleName($iniFile);
        if ($moduleName === '') {
            return parent::enableIniFile($iniFile);
        }
        $this->readModuleIniFile($iniFile);
        if ($this->modsAvailable->isEnabled($moduleName)) {
            return false;
        }
        $this->modsAvailable->enable($moduleName);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Installer\Enabler::disableIniFile()
     */
    public function disableIniFile($iniFile)
    {
        $moduleName = $this->getModuleName($iniFile);
        if ($moduleName === '') {
            return parent::disableIniFile($iniFile);
        }
        $this->readModuleIniFile($iniFile);
        if (!$this->modsAvailable->isEnabled($moduleName)) {
            return false;
        }
        $this->modsAvailable->disable($moduleName);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Installer\Enabler::writeIniFile()
     */
    protected function writeIniFile(CompiledExtension $compiled, $iniFile, $iniContent)
    {
        $moduleName = $compiled->getModuleName();
        $iniFile = $this->modsAvailable->writeIniFile($moduleName, self::getPriority($compiled->getResolved()), $iniContent);
        $this->logger->debug("Created {$iniFile}, enabling it", ['content' => $iniContent]);
        $this->modsAvailable->enable($moduleName);

        return $iniFile;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Installer\Enabler::disableEnabledModule()
     */
    protected function disableEnabledModule($moduleName, $iniFile, $iniContent)
    {
        // The re-enabled extensions are identified by their handle
        $iniModuleName = $this->getModuleName($iniFile);
        $this->modsAvailable->disable($iniModuleName === '' ? $moduleName : $iniModuleName);

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Installer\Enabler::getDisabledIniFile()
     */
    public function getDisabledIniFile(Extension $extension)
    {
        foreach (array_unique(array_merge([$extension::getHandle()], $extension::getInstalledNames())) as $moduleName) {
            if (!preg_match('/^[\w-]+$/D', $moduleName)) {
                continue;
            }
            $iniFile = $this->modsAvailable->getIniFile($moduleName);
            if ($this->fs->isFile($iniFile) && $this->hasModuleFiles($this->fs->readFile($iniFile)) && !$this->modsAvailable->isEnabled($moduleName)) {
                return $iniFile;
            }
        }

        return '';
    }

    /**
     * The ini files of the packages that have been removed stay until the packages are purged.
     *
     * @param string $iniContent
     *
     * @return bool
     */
    private function hasModuleFiles($iniContent)
    {
        if (!preg_match_all('/^[ \t]*;?[ \t]*(zend_)?extension[ \t]*=[ \t]*"?([^"\s;]+)/m', $iniContent, $matches, PREG_SET_ORDER)) {
            return false;
        }
        foreach ($matches as $match) {
            if (!$this->moduleExists($match[2], $match[1] !== '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param non-empty-string $value the value of extension= or zend_extension=
     * @param bool $zendExtension
     *
     * @return bool
     */
    private function moduleExists($value, $zendExtension)
    {
        if ($value[0] === '/') {
            return $this->fs->isFile($value);
        }
        // PHP resolves it against the current directory
        if (!$zendExtension && strpos($value, '/') !== false) {
            return false;
        }
        $path = "{$this->php->getExtensionDir()}/{$value}";
        if ($this->fs->isFile($path)) {
            return true;
        }
        // Since PHP 7.2 the modules can be specified by name
        if ($this->php->getMajorMinorVersion() >= 702 && $this->fs->isFile("{$path}.so")) {
            return true;
        }

        return false;
    }

    /**
     * The ini files outside the mods-available directory (for example, the ones created by the installers of the vendors) are handled as usual.
     *
     * @param non-empty-string $iniFile
     *
     * @return string empty string if the file is not in the mods-available directory
     */
    private function getModuleName($iniFile)
    {
        if (dirname($iniFile) !== $this->modsAvailable->getDirectory() || substr($iniFile, -4) !== '.ini') {
            return '';
        }

        return basename($iniFile, '.ini');
    }
}
