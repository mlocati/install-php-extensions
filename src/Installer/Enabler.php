<?php

namespace IPE\Installer;

use IPE\Exception;
use IPE\Extension;
use IPE\Extension\Manifest;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\PHPInfo;
use IPE\Service\Regex;

/**
 * Enables a compiled extension by creating an ini file in the directory scanned by PHP.
 */
class Enabler
{
    /**
     * @var \IPE\Logger
     */
    protected $logger;

    /**
     * The extension= and zend_extension= lines (with \s the ^ could match the empty lines before them).
     *
     * @var non-empty-string
     */
    const RX_ACTIVE_LINE = '/^([ \t]*)((?:zend_)?extension[ \t]*=[ \t]*\S)/m';

    /**
     * The commented out extension= and zend_extension= lines.
     *
     * @var non-empty-string
     */
    const RX_INACTIVE_LINE = '/^([ \t]*);[ \t]*((?:zend_)?extension[ \t]*=[ \t]*\S)/m';

    /**
     * The names of the ini files used by docker-php-ext-enable (xx-php-ext-<name> for the extensions loaded after the others).
     *
     * @var non-empty-string
     */
    const INI_BASENAME_COMPAT = 'compat';

    /**
     * The names of the ini files used by pie.
     *
     * @var non-empty-string
     */
    const INI_BASENAME_DEFAULT = '{priority}-{name}';

    /**
     * {priority} must come before {name}, so that the files are sorted by priority.
     * No leading dot (hidden files), no trailing dot (it would be followed by .ini).
     *
     * @var non-empty-string
     */
    const RX_VALID_INI_BASENAME = '/^(?:[\w-][\w.-]*)?\{priority\}[\w.-]*\{name\}(?:[\w.-]*[\w-])?$/D';

    /**
     * @var \IPE\PHPInfo
     */
    protected $php;

    /**
     * @var \IPE\Filesystem
     */
    protected $fs;

    /**
     * @var \IPE\Installer\Enabler\Options
     */
    protected $options;

    /**
     * @var array<non-empty-string, array{non-empty-string, string}> the keys are the module names (the handles for the re-enabled extensions), the values are the path and the content of the ini files
     */
    private $enabledIniFiles = [];

    public function __construct(PHPInfo $php, Filesystem $fs, \IPE\Logger $logger, Enabler\Options $options)
    {
        $this->logger = $logger;
        $this->php = $php;
        $this->fs = $fs;
        $this->options = $options;
    }

    /**
     * Check that the module loads, then create the ini file that enables it.
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-list<non-empty-string> the names of the modules provided by the extension, as seen by PHP
     */
    public function enable(CompiledExtension $compiled)
    {
        $iniScanDir = $this->php->getIniScanDir();
        if ($iniScanDir === '') {
            throw new Exception\IO('PHP is not configured to scan a directory for additional ini files');
        }
        $iniBasename = $this->buildIniBasename($compiled);
        $iniContent = $this->buildIniContent($compiled);
        if ($this->options->shouldSkipCheck()) {
            return $this->enableWithoutCheck($compiled, "{$iniScanDir}/{$iniBasename}.ini", $iniContent);
        }
        $this->logger->debug("Checking if the module {$compiled->getModuleName()} can be loaded");
        list($loadedBefore) = $this->listLoadedModules();
        list(, $outputBefore) = $this->runEmptyScript();
        $temporaryIniFile = "{$iniScanDir}/{$iniBasename}--temp.ini";
        $this->fs->writeFile($temporaryIniFile, $iniContent);
        try {
            list($loadedAfter, $errors) = $this->listLoadedModules();
            list($rcAfter, $outputAfter) = $this->runEmptyScript();
        } finally {
            $this->fs->deleteFile($temporaryIniFile);
        }
        $newModules = array_values(array_udiff($loadedAfter, $loadedBefore, 'strcasecmp'));
        if ($newModules === []) {
            // The official ZTS Docker images are compiled with --disable-zend-signals
            $hint = strpos($errors, 'zend_signal_globals_offset') === false ? '' : "\nThis module requires a PHP compiled with --enable-zend-signals.";

            throw new Exception\IO("The module {$compiled->getModuleName()} does not load" . ($errors === '' ? '' : ":\n{$errors}") . $hint);
        }
        if (substr_count($outputAfter, 'undefined symbol') > substr_count($outputBefore, 'undefined symbol')) {
            throw new Exception\IO("The module {$compiled->getModuleName()} uses functions that are not available, so PHP would crash when calling them:\n{$outputAfter}");
        }
        if ($rcAfter !== 0) {
            throw new Exception\IO("The module {$compiled->getModuleName()} loads, but PHP exits with the code {$rcAfter}" . ($outputAfter === '' ? '' : ":\n{$outputAfter}"));
        }
        if (self::withoutNumbers($outputAfter) !== self::withoutNumbers($outputBefore)) {
            throw new Exception\IO("The module {$compiled->getModuleName()} loads, but it changes the output of PHP:\n{$outputAfter}");
        }
        $iniFile = $this->writeIniFile($compiled, "{$iniScanDir}/{$iniBasename}.ini", $iniContent);
        $this->addLoadedModules($newModules);
        $this->enabledIniFiles[$compiled->getModuleName()] = [$iniFile, $iniContent];

        return $newModules;
    }

    /**
     * Create the ini file that enables the module.
     *
     * @param non-empty-string $iniFile the ini file in the directory scanned by PHP
     * @param non-empty-string $iniContent
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string the full path of the created ini file
     */
    protected function writeIniFile(CompiledExtension $compiled, $iniFile, $iniContent)
    {
        $this->logger->debug("Creating {$iniFile}", ['content' => $iniContent]);
        $this->fs->writeFile($iniFile, $iniContent);

        return $iniFile;
    }

    /**
     * @param non-empty-string $iniFile
     * @param non-empty-string $iniContent
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-list<non-empty-string>
     */
    private function enableWithoutCheck(CompiledExtension $compiled, $iniFile, $iniContent)
    {
        list($loadedBefore) = $this->listLoadedModules();
        $this->logger->debug("Enabling the module {$compiled->getModuleName()} without checking if it can be loaded");
        $iniFile = $this->writeIniFile($compiled, $iniFile, $iniContent);
        list($loadedAfter) = $this->listLoadedModules();
        $newModules = array_values(array_udiff($loadedAfter, $loadedBefore, 'strcasecmp'));
        if ($newModules === []) {
            // The module may not load in this environment
            $newModules = [$compiled->getModuleName()];
        }
        $this->addLoadedModules($newModules);
        $this->enabledIniFiles[$compiled->getModuleName()] = [$iniFile, $iniContent];

        return $newModules;
    }

    /**
     * Find the ini file that loads (or would load, if it's disabled) an installed extension.
     *
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if not found
     */
    public function findIniFile(Extension $extension)
    {
        $moduleNames = array_merge([$extension::getHandle()], $extension::getInstalledNames());
        $match = null;
        foreach ($this->php->getIniScanDirs() as $iniScanDir) {
            if (!$this->fs->isDirectory($iniScanDir)) {
                continue;
            }
            foreach ($this->fs->listDirectoryContents($iniScanDir) as $name) {
                if (substr($name, -4) !== '.ini') {
                    continue;
                }
                $iniFile = "{$iniScanDir}/{$name}";
                if (!preg_match_all('/^[ \t]*;?[ \t]*(?:zend_)?extension[ \t]*=[ \t]*"?([^"\s;]+)/m', $this->fs->readFile($iniFile), $match)) {
                    continue;
                }
                foreach ($match[1] as $module) {
                    if (in_array(strtolower(basename($module, '.so')), array_map('strtolower', $moduleNames), true)) {
                        return $iniFile;
                    }
                }
            }
        }

        return '';
    }

    /**
     * Find the ini file of an extension that's installed but disabled.
     *
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if not found
     */
    public function getDisabledIniFile(Extension $extension)
    {
        return '';
    }

    /**
     * Enable the ini file returned by getDisabledIniFile(), and check that PHP loads the extension.
     *
     * @param non-empty-string $iniFile
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-list<non-empty-string> the names of the modules provided by the extension, as seen by PHP
     */
    public function reenable(Extension $extension, $iniFile)
    {
        list($loadedBefore) = $this->listLoadedModules();
        $this->enableIniFile($iniFile);
        list($loadedAfter, $errors) = $this->listLoadedModules();
        $newModules = array_values(array_udiff($loadedAfter, $loadedBefore, 'strcasecmp'));
        if ($newModules === []) {
            if (!$this->options->shouldSkipCheck()) {
                $this->disableIniFile($iniFile);

                throw new Exception\IO('The extension ' . $extension::getHandle() . ' does not load' . ($errors === '' ? '' : ":\n{$errors}"));
            }
            $newModules = array_values($extension::getInstalledNames());
        }
        $this->php->addInstalledExtensions($newModules);
        $this->enabledIniFiles[$extension::getHandle()] = [$iniFile, $this->readModuleIniFile($iniFile)];

        return $newModules;
    }

    /**
     * Get the full path of the ini file that enable() creates.
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    public function getIniFile(CompiledExtension $compiled)
    {
        return "{$this->php->getIniScanDir()}/{$this->buildIniBasename($compiled)}.ini";
    }

    /**
     * Disable the extensions enabled so far, and create the scripts that enable them again (if requested in the options).
     *
     * @throws \IPE\Exception\IO
     *
     * @return array<string, non-empty-string> the keys are the module names, the values are the full paths of the scripts that enable them
     */
    public function disableEnabledExtensions()
    {
        $result = [];
        foreach ($this->enabledIniFiles as $moduleName => list($iniFile, $iniContent)) {
            $script = $this->disableEnabledModule($moduleName, $iniFile, $iniContent);
            if ($script !== '') {
                $result[$moduleName] = $script;
            }
        }
        $this->enabledIniFiles = [];

        return $result;
    }

    /**
     * Disable a module enabled by enable() or reenable().
     *
     * @param non-empty-string $moduleName the handle of the extension for reenable()
     * @param non-empty-string $iniFile the value returned by writeIniFile(), or the ini file passed to reenable()
     * @param string $iniContent
     *
     * @throws \IPE\Exception\IO
     *
     * @return string the full path of the script that enables the module again (empty string if none)
     */
    protected function disableEnabledModule($moduleName, $iniFile, $iniContent)
    {
        $this->fs->writeFile($iniFile, Regex::replace(self::RX_ACTIVE_LINE, '\1;\2', $iniContent));

        return $this->options->shouldCreateEnableScripts() ? $this->createEnableScript($moduleName, $iniFile) : '';
    }

    /**
     * Is the ini file loading a module (that is, are the extension= or zend_extension= lines not commented out)?
     *
     * @param non-empty-string $iniFile
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    public function isIniFileActive($iniFile)
    {
        return preg_match(self::RX_ACTIVE_LINE, $this->fs->readFile($iniFile)) === 1;
    }

    /**
     * Uncomment the extension= and zend_extension= lines of an ini file.
     *
     * @param non-empty-string $iniFile
     *
     * @throws \IPE\Exception\IO if the file doesn't load any module
     *
     * @return bool false if the ini file was already active
     */
    public function enableIniFile($iniFile)
    {
        $content = $this->readModuleIniFile($iniFile);
        if (!preg_match(self::RX_INACTIVE_LINE, $content)) {
            return false;
        }
        $this->logger->debug("Uncommenting the extension lines of {$iniFile}");
        $this->fs->writeFile($iniFile, Regex::replace(self::RX_INACTIVE_LINE, '\1\2', $content));

        return true;
    }

    /**
     * Comment out the extension= and zend_extension= lines of an ini file.
     *
     * @param non-empty-string $iniFile
     *
     * @throws \IPE\Exception\IO if the file doesn't load any module
     *
     * @return bool false if the ini file was already inactive
     */
    public function disableIniFile($iniFile)
    {
        $content = $this->readModuleIniFile($iniFile);
        if (!preg_match(self::RX_ACTIVE_LINE, $content)) {
            return false;
        }
        $this->logger->debug("Commenting out the extension lines of {$iniFile}");
        $this->fs->writeFile($iniFile, Regex::replace(self::RX_ACTIVE_LINE, '\1;\2', $content));

        return true;
    }

    /**
     * @param non-empty-string $iniFile
     *
     * @throws \IPE\Exception\IO if the file doesn't exist or it doesn't load any module
     *
     * @return string
     */
    protected function readModuleIniFile($iniFile)
    {
        if (!$this->fs->isFile($iniFile)) {
            throw new Exception\IO("The file {$iniFile} does not exist");
        }
        $content = $this->fs->readFile($iniFile);
        if (!preg_match(self::RX_ACTIVE_LINE, $content) && !preg_match(self::RX_INACTIVE_LINE, $content)) {
            throw new Exception\IO("The file {$iniFile} does not contain extension= or zend_extension= lines");
        }

        return $content;
    }

    /**
     * Check that an extension enabled by the installer of its vendor is actually loaded.
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-list<non-empty-string> the names of the modules provided by the extension, as seen by PHP
     */
    public function checkEnabled(Extension $extension)
    {
        list($loaded, $errors) = $this->listLoadedModules();
        $names = array_values(array_uintersect($loaded, $extension::getInstalledNames(), 'strcasecmp'));
        if ($names === [] && $this->options->shouldSkipCheck()) {
            $names = array_values($extension::getInstalledNames());
        }
        if ($names === []) {
            throw new Exception\IO('The extension ' . $extension::getHandle() . ' does not load' . ($errors === '' ? '' : ":\n{$errors}"));
        }
        $this->addLoadedModules($names);

        return $names;
    }

    /**
     * Write a shell script (next to the php binary) that removes the ; from the ini file.
     *
     * @param non-empty-string $moduleName
     * @param non-empty-string $iniFile
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string the full path of the script
     */
    private function createEnableScript($moduleName, $iniFile)
    {
        $scriptName = "docker-php-ext-enable-{$moduleName}";
        $script = dirname($this->php->getPHPBin()) . "/{$scriptName}";
        $quotedIniFile = $this->fs->escapeShellArg($iniFile);
        $this->fs->writeFile(
            $script,
            "#!/bin/sh\n\nif grep -q -e '^;extension=' -e '^;zend_extension=' {$quotedIniFile}; then\n\techo 'Enabling the extension {$moduleName}'\n\tsed -i -e 's/^;extension=/extension=/' -e 's/^;zend_extension=/zend_extension=/' {$quotedIniFile}\nelse\n\techo 'The extension {$moduleName} has already been enabled'\nfi\n"
        );
        list($rc, $output) = $this->fs->run(Command::create('chmod', ['+x', $script]));
        if ($rc !== 0) {
            throw new Exception\IO("Failed to make {$script} executable:\n{$output}");
        }
        $this->logger->notice("The extension {$moduleName} has not been enabled: you can enable it later by running {$scriptName}");

        return $script;
    }

    /**
     * @return non-empty-string
     */
    private function buildIniBasename(CompiledExtension $compiled)
    {
        $moduleName = $compiled->getModuleName();
        $priority = self::getPriority($compiled->getResolved());
        $iniBasename = $this->options->getIniBasename();
        if ($iniBasename === self::INI_BASENAME_COMPAT) {
            return ($priority > Manifest::DEFAULT_PRIORITY ? 'xx-php-ext-' : 'docker-php-ext-') . $moduleName;
        }

        return strtr($iniBasename, [
            '{priority}' => sprintf('%02d', $priority),
            '{name}' => $moduleName,
        ]);
    }

    /**
     * The priority declared by the maintainers of the extension wins over the one of the known extension.
     *
     * @return int
     */
    protected static function getPriority(Extension\Resolved $resolved)
    {
        $manifest = $resolved->getManifest();
        $priority = $manifest === null ? null : $manifest->getPriority();
        if ($priority !== null) {
            return $priority;
        }
        $known = $resolved->getKnownExtension();
        if ($known instanceof Extension\DeclaresPriority) {
            return $known::getPriority();
        }

        return Manifest::DEFAULT_PRIORITY;
    }

    /**
     * @return non-empty-string
     */
    private function buildIniContent(CompiledExtension $compiled)
    {
        if ($compiled->isZendExtension()) {
            // zend_extension accepts paths relative to extension_dir only since PHP 7.2
            $value = $this->php->getMajorMinorVersion() < 702 ? $compiled->getModulePath() : basename($compiled->getModulePath());

            return "zend_extension={$value}\n{$compiled->getIniSettings()}";
        }

        return 'extension=' . basename($compiled->getModulePath()) . "\n{$compiled->getIniSettings()}";
    }

    /**
     * Some modules print values that change at every run (ddtrace prints the PID, for example).
     *
     * @param string $output
     *
     * @throws \IPE\Exception\Regex
     *
     * @return string
     */
    private static function withoutNumbers($output)
    {
        return Regex::replace('/\d+(?:\.\d+)*/', '<number>', $output);
    }

    /**
     * Get how PHP behaves when it has nothing to do.
     *
     * @throws \IPE\Exception\IO
     *
     * @return array{int, string} the exit code, and the output (stdout and stderr)
     */
    private function runEmptyScript()
    {
        // With lazy binding (the default), missing symbols are detected only when they are used
        return $this->runPHP(['-r', 'return;'], ['LD_BIND_NOW' => '1']);
    }

    /**
     * @param non-empty-list<non-empty-string> $names the names of the modules that PHP now loads
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function addLoadedModules(array $names)
    {
        $this->php->addInstalledExtensions($names);
        if ($this->php->getSSLLibrary() === null && in_array('openssl', array_map('strtolower', $names), true)) {
            $this->php->detectSSLLibrary($this->fs);
        }
    }

    /**
     * Run php -m and split the output into the loaded module names and the startup errors.
     *
     * @throws \IPE\Exception\IO
     *
     * @return array{list<non-empty-string>, string}
     */
    private function listLoadedModules()
    {
        list(, $output) = $this->runPHP(['-m']);
        $modules = [];
        $errors = [];
        $inList = false;
        foreach (preg_split('/[\r\n]+/', $output) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\[.+\]$/', $line)) {
                $inList = true;
            } elseif ($inList) {
                $modules[] = $line;
            } else {
                $errors[] = $line;
            }
        }

        // Some modules are listed both in [PHP Modules] and in [Zend Modules]
        return [array_values(array_unique($modules)), implode("\n", $errors)];
    }

    /**
     * @param non-empty-list<non-empty-string> $arguments
     * @param array<non-empty-string, string> $environment
     *
     * @throws \IPE\Exception\IO
     *
     * @return array{int, string} the exit code, and the output (stdout and stderr)
     */
    private function runPHP(array $arguments, array $environment = [])
    {
        $options = ['-d', 'display_startup_errors=1', '-d', 'display_errors=1', '-d', 'error_reporting=-1'];
        if ($this->php->getMajorMinorVersion() < 700) {
            // PHP 5 warns about the missing timezone when a module (for example seaslog) uses the date functions at startup
            $options = array_merge($options, ['-d', 'date.timezone=UTC']);
        }

        return $this->fs->run(Command::create($this->php->getPHPBin(), array_merge($options, $arguments))->withEnvironment($environment));
    }
}
