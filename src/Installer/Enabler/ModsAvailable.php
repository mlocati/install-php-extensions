<?php

namespace IPE\Installer\Enabler;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\PHPInfo;

/**
 * The PHP packages of Debian and Ubuntu keep the ini files in a mods-available directory, and phpenmod links them in the conf.d directory of every SAPI.
 */
final class ModsAvailable
{
    /**
     * The start of the first line of the ini files that we can overwrite.
     *
     * @var non-empty-string
     */
    const MARKER = '; Created by install-php-extensions';

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var non-empty-string
     */
    private $directory;

    /**
     * @var non-empty-string
     */
    private $sapi;

    /**
     * @var string empty string for PHP 5, which has only one version and whose commands don't accept -v
     */
    private $version;

    /**
     * @var non-empty-string the directory and the prefix of the commands (for example /usr/sbin/php or /usr/sbin/php5)
     */
    private $commandPrefix;

    /**
     * @param non-empty-string $directory
     * @param non-empty-string $sapi
     * @param string $version
     * @param non-empty-string $commandPrefix
     */
    private function __construct(Filesystem $fs, $directory, $sapi, $version, $commandPrefix)
    {
        $this->fs = $fs;
        $this->directory = $directory;
        $this->sapi = $sapi;
        $this->version = $version;
        $this->commandPrefix = $commandPrefix;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return self|null NULL if PHP doesn't use a mods-available directory
     */
    public static function detect(PHPInfo $php, Filesystem $fs)
    {
        $iniScanDir = $php->getIniScanDir();
        $match = null;
        // For example /etc/php/8.3/cli/conf.d or /etc/php5/cli/conf.d
        if (!preg_match('#^(?<base>/.*/php(?<php5>5)?(?:/(?<version>\d+\.\d+))?)/(?<sapi>[\w-]+)/conf\.d$#D', $iniScanDir, $match)) {
            return null;
        }
        $php5 = $match['php5'] === '5';
        if ($php5 === ($match['version'] !== '')) {
            return null;
        }
        $directory = "{$match['base']}/mods-available";
        if (!$fs->isDirectory($directory)) {
            return null;
        }
        $enmod = $fs->resolveCommand($php5 ? 'php5enmod' : 'phpenmod');
        if ($enmod === '') {
            return null;
        }

        return new self($fs, $directory, $match['sapi'], $match['version'], dirname($enmod) . ($php5 ? '/php5' : '/php'));
    }

    /**
     * @return non-empty-string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * @param non-empty-string $moduleName
     *
     * @return non-empty-string
     */
    public function getIniFile($moduleName)
    {
        return "{$this->directory}/{$moduleName}.ini";
    }

    /**
     * @param non-empty-string $moduleName
     *
     * @throws \IPE\Exception\IO if the ini file exists and it hasn't been created by us
     *
     * @return void
     */
    public function checkIniFileWritable($moduleName)
    {
        $iniFile = $this->getIniFile($moduleName);
        if (!$this->fs->exists($iniFile)) {
            return;
        }
        if (strpos($this->fs->readFile($iniFile), self::MARKER) !== 0) {
            throw new Exception\IO("The file {$iniFile} already exists, and it doesn't start with the line '" . self::MARKER . "': remove or rename it to install the module {$moduleName}");
        }
    }

    /**
     * @param non-empty-string $moduleName
     * @param int $priority
     * @param non-empty-string $content
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string the full path of the ini file
     */
    public function writeIniFile($moduleName, $priority, $content)
    {
        $this->checkIniFileWritable($moduleName);
        $iniFile = $this->getIniFile($moduleName);
        $this->fs->writeFile($iniFile, self::MARKER . ", which may overwrite this file: remove this line to prevent it\n; priority=" . sprintf('%02d', $priority) . "\n{$content}");

        return $iniFile;
    }

    /**
     * Enable the module for all the SAPIs, including the ones that will be installed later.
     *
     * @param non-empty-string $moduleName
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function enable($moduleName)
    {
        // php5enmod registers the module only if no SAPI is specified, and with -r it only registers it
        list(, $output) = $this->fs->run(Command::create($this->commandPrefix . 'enmod', $this->version === '' ? [$moduleName] : ['-v', $this->version, '-r', $moduleName]));
        // phpenmod always exits with 0
        if (!$this->isEnabled($moduleName)) {
            throw new Exception\IO("Failed to enable the module {$moduleName} with {$this->commandPrefix}enmod" . ($output === '' ? '' : ":\n{$output}"));
        }
    }

    /**
     * Disable the module for all the SAPIs, including the ones that will be installed later.
     *
     * @param non-empty-string $moduleName
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function disable($moduleName)
    {
        list(, $output) = $this->fs->run(Command::create($this->commandPrefix . 'dismod', $this->version === '' ? [$moduleName] : ['-v', $this->version, '-r', $moduleName]));
        if ($this->isEnabled($moduleName)) {
            throw new Exception\IO("Failed to disable the module {$moduleName} with {$this->commandPrefix}dismod" . ($output === '' ? '' : ":\n{$output}"));
        }
    }

    /**
     * Is the module enabled for the SAPI that we are using?
     *
     * @param non-empty-string $moduleName
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    public function isEnabled($moduleName)
    {
        $arguments = $this->version === '' ? [] : ['-v', $this->version];
        list($rc) = $this->fs->run(Command::create($this->commandPrefix . 'query', array_merge($arguments, ['-s', $this->sapi, '-q', '-m', $moduleName]))->withOutput(Command::OUTPUT_DISCARD));

        return $rc === 0;
    }
}
