<?php

namespace IPE\Installer;

use IPE\Exception;
use IPE\Extension;
use IPE\Extension\Resolved;
use IPE\Installer\Report\InstalledExtension;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependency;

/**
 * Installs resolved extensions: system packages and dependencies, compilation or binary installation, activation and cleanup.
 */
final class ExtensionsInstaller
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var \IPE\OS\Posix
     */
    private $os;

    /**
     * @var \IPE\PHPInfo
     */
    private $php;

    /**
     * @var \IPE\SystemDependency\Registry
     */
    private $dependencyRegistry;

    /**
     * @var \IPE\Installer\Compiler
     */
    private $compiler;

    /**
     * @var \IPE\Installer\BinaryInstaller
     */
    private $binaryInstaller;

    /**
     * @var \IPE\Installer\VendorArchivePreparer
     */
    private $vendorArchivePreparer;

    /**
     * @var \IPE\Installer\Enabler
     */
    private $enabler;

    /**
     * @var \IPE\Extension\Asked\Resolver
     */
    private $resolver;

    public function __construct(OS\Posix $os, PHPInfo $php, SystemDependency\Registry $dependencyRegistry, Compiler $compiler, BinaryInstaller $binaryInstaller, VendorArchivePreparer $vendorArchivePreparer, Enabler $enabler, Extension\Asked\Resolver $resolver, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->os = $os;
        $this->php = $php;
        $this->dependencyRegistry = $dependencyRegistry;
        $this->compiler = $compiler;
        $this->binaryInstaller = $binaryInstaller;
        $this->vendorArchivePreparer = $vendorArchivePreparer;
        $this->enabler = $enabler;
        $this->resolver = $resolver;
    }

    /**
     * Resolve and install the asked extensions.
     *
     * @param \IPE\Extension\Asked[] $askedExtensions
     * @param array<string, array<string, string|true>> $systemDependencyOptions the keys are the handles of the system dependencies
     * @param bool $enable false to disable the extensions at the end of the installation (Enabler::enableIniFile() can enable them later)
     * @param bool $deleteSystemPackagerCache
     *
     * @throws \IPE\Exception
     *
     * @return \IPE\Installer\Report
     */
    public function installAsked(array $askedExtensions, array $systemDependencyOptions = [], $enable = true, $deleteSystemPackagerCache = true)
    {
        $state = (new State($this->php, $this->os->getFilesystem()))->withSystemDependencyOptions($systemDependencyOptions);

        return $this->install($this->resolver->resolveAskedExtensions($askedExtensions, $state), $state, $enable, $deleteSystemPackagerCache);
    }

    /**
     * @param \IPE\Extension\Resolved[] $resolvedExtensions
     * @param bool $enable false to disable the extensions at the end of the installation (Enabler::enableIniFile() can enable them later)
     * @param bool $deleteSystemPackagerCache
     *
     * @throws \IPE\Exception
     *
     * @return \IPE\Installer\Report
     */
    public function install(array $resolvedExtensions, State $state, $enable = true, $deleteSystemPackagerCache = true)
    {
        $this->dependencyRegistry->setOptions($state);
        $systemPackager = $this->os->getSystemPackager();
        $dependencies = $this->dependencyRegistry->resolveDependencies($this->collectDependencyHandles($resolvedExtensions));
        $packages = $this->dependencyRegistry->collectSystemPackages($dependencies, $state);
        foreach ($resolvedExtensions as $resolvedExtension) {
            $origin = $resolvedExtension->getOrigin();
            if ($origin === Resolved::ORIGIN_DISABLED) {
                continue;
            }
            if ($origin !== Resolved::ORIGIN_BINARY || $resolvedExtension->getKnownExtension() instanceof Extension\BuildsModule) {
                $packages = $packages->withVolatile($systemPackager->getPhpizeRequirements());
                break;
            }
        }
        $dependencyInstaller = new DependencyInstaller($systemPackager, $this->logger);
        $installedDependencies = $dependencyInstaller->install($packages);
        $compiledLibraries = [];
        $installedExtensions = [];
        $moduleNames = [];
        try {
            foreach ($dependencies as $dependency) {
                $dependency->install($state);
                if ($dependency instanceof SystemDependency\CompiledLibrary && $dependency->getCompiledSourceURL() !== '') {
                    $compiledLibraries[] = new Report\CompiledLibrary($dependency::getHandle(), $dependency->getCompiledVersion(), $dependency->getCompiledSourceURL());
                }
            }
            foreach ($resolvedExtensions as $resolvedExtension) {
                list($installedExtensions[], $moduleNames[]) = $this->installExtension($resolvedExtension, $state, $enable);
            }
            foreach ($dependencies as $dependency) {
                $dependency->finalize($state);
            }
            if (!$enable) {
                $enableScripts = $this->enabler->disableEnabledExtensions();
                foreach ($moduleNames as $index => $moduleName) {
                    if (isset($enableScripts[$moduleName])) {
                        $installedExtensions[$index] = $installedExtensions[$index]->withEnableScript($enableScripts[$moduleName]);
                    }
                }
            }
        } finally {
            $dependencyInstaller->cleanup($installedDependencies, $deleteSystemPackagerCache);
        }

        $extensions = [];
        foreach ($installedExtensions as $installedExtension) {
            $extensions[] = $this->withActualIniState($installedExtension);
        }

        return new Report($installedDependencies, $compiledLibraries, $extensions, $state->getSkippedExtensions());
    }

    /**
     * @param \IPE\Extension\Resolved[] $resolvedExtensions
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedOS
     *
     * @return list<non-empty-string>
     */
    private function collectDependencyHandles(array $resolvedExtensions)
    {
        $result = [];
        foreach ($resolvedExtensions as $resolvedExtension) {
            if ($resolvedExtension->getOrigin() === Resolved::ORIGIN_DISABLED) {
                continue;
            }
            $knownExtension = $resolvedExtension->getKnownExtension();
            if ($knownExtension instanceof Extension\RequiresSystemDependencies) {
                $result = array_merge($result, $knownExtension->getRequiredSystemDependencies($resolvedExtension->getVersion(), $resolvedExtension->getOptionValues()));
            }
            $manifest = $resolvedExtension->getManifest();
            if ($manifest !== null) {
                // The manifest reader strips the lib- prefix of the composer platform packages
                foreach (array_keys($manifest->getRequiredLibraries()) as $library) {
                    $result[] = "lib-{$library}";
                }
            }
        }

        return $result;
    }

    /**
     * @param bool $enable
     *
     * @throws \IPE\Exception
     *
     * @return array{\IPE\Installer\Report\InstalledExtension, string} the installed extension, and the name of the module enabled by IPE (empty string if the installer of the vendor enabled it)
     */
    private function installExtension(Resolved $resolvedExtension, State $state, $enable)
    {
        $startedAt = microtime(true);
        $knownExtension = $resolvedExtension->getKnownExtension();
        if ($resolvedExtension->getOrigin() === Resolved::ORIGIN_DISABLED && $knownExtension !== null) {
            $iniFile = $resolvedExtension->getPath();
            $this->logger->info("Enabling {$iniFile}");
            $loadedModules = $this->enabler->reenable($knownExtension, $iniFile);
            $this->logger->info('Enabled: ' . implode(', ', $loadedModules));

            return [new InstalledExtension($resolvedExtension, InstalledExtension::METHOD_ENABLED, [], null, [$iniFile => true], $loadedModules, [], microtime(true) - $startedAt), ''];
        }
        if ($resolvedExtension->getOrigin() === Resolved::ORIGIN_BINARY) {
            $this->logger->info("Installing {$resolvedExtension}");
            $filesBefore = $this->listModuleAndIniFiles();
            $compiledExtension = $this->binaryInstaller->install($resolvedExtension);
            if ($compiledExtension === null) {
                if (!$enable) {
                    $this->logger->warning("The installer of the vendor of {$resolvedExtension} always enables the extension");
                }
                $loadedModules = [];
                if ($knownExtension !== null) {
                    $loadedModules = $this->enabler->checkEnabled($knownExtension);
                    $this->logger->info('Enabled by the installer of the vendor: ' . implode(', ', $loadedModules));
                }
                $moduleFiles = [];
                $iniFiles = [];
                foreach (array_keys(array_diff_key($this->listModuleAndIniFiles(), $filesBefore)) as $file) {
                    if (substr($file, -4) === '.ini') {
                        $iniFiles[$file] = true;
                    } elseif ($file !== '') {
                        $moduleFiles[] = $file;
                    }
                }

                return [new InstalledExtension($resolvedExtension, InstalledExtension::METHOD_VENDOR_INSTALLER, $moduleFiles, null, $iniFiles, $loadedModules, [], microtime(true) - $startedAt), ''];
            }
            $method = InstalledExtension::METHOD_BINARY;
        } else {
            if ($resolvedExtension->getOrigin() === Resolved::ORIGIN_VENDOR_ARCHIVE) {
                $resolvedExtension = $this->vendorArchivePreparer->prepare($resolvedExtension, $state);
            }
            $this->logger->info("Compiling {$resolvedExtension}");
            $compiledExtension = $this->compiler->compile($resolvedExtension);
            $method = InstalledExtension::METHOD_COMPILED;
        }
        $this->logger->info("Enabling the module {$compiledExtension->getModuleName()}");
        $loadedModules = $this->enabler->enable($compiledExtension);
        $this->logger->info('Enabled: ' . implode(', ', $loadedModules));
        $installed = new InstalledExtension(
            $resolvedExtension,
            $method,
            [$compiledExtension->getModulePath()],
            $compiledExtension->isZendExtension(),
            [$this->enabler->getIniFile($compiledExtension) => true],
            $loadedModules,
            $compiledExtension->getConfigureArguments(),
            microtime(true) - $startedAt
        );

        return [$installed, $compiledExtension->getModuleName()];
    }

    /**
     * Installers of vendors don't tell which files they create.
     *
     * @throws \IPE\Exception\IO
     *
     * @return array<string, true> the keys are the full paths of the module files in the PHP extension directory, and of the ini files in the directories scanned by PHP
     */
    private function listModuleAndIniFiles()
    {
        $fs = $this->os->getFilesystem();
        $result = [];
        $directories = [[$this->php->getExtensionDir(), '.so']];
        foreach ($this->php->getIniScanDirs() as $iniScanDir) {
            $directories[] = [$iniScanDir, '.ini'];
        }
        foreach ($directories as list($directory, $suffix)) {
            if (!$fs->isDirectory($directory)) {
                continue;
            }
            foreach ($fs->listDirectoryContents($directory) as $name) {
                if (substr($name, -strlen($suffix)) === $suffix) {
                    $result["{$directory}/{$name}"] = true;
                }
            }
        }

        return $result;
    }

    /**
     * The ini files are commented out when the extensions must not be enabled, and the installers of the vendors may create them disabled.
     *
     * @return \IPE\Installer\Report\InstalledExtension
     */
    private function withActualIniState(InstalledExtension $installed)
    {
        $iniFiles = [];
        foreach ($installed->getIniFiles() as $iniFile => $active) {
            try {
                $iniFiles[$iniFile] = $this->enabler->isIniFileActive($iniFile);
            } catch (Exception\IO $x) {
                $iniFiles[$iniFile] = $active;
            }
        }

        return $installed->withIniFiles($iniFiles);
    }
}
