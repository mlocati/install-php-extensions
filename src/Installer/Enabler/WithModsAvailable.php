<?php

namespace IPE\Installer\Enabler;

use IPE\Exception;
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
        $this->modsAvailable->disable($moduleName);

        return '';
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
