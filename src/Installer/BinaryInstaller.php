<?php

namespace IPE\Installer;

use IPE\Exception;
use IPE\Extension;
use IPE\Extension\Resolved;
use IPE\Filesystem;
use IPE\PHPInfo;

/**
 * Installs a resolved extension that's available in binary form.
 */
final class BinaryInstaller
{
    /**
     * @var \IPE\PHPInfo
     */
    private $php;

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var \IPE\Installer\BuildTools
     */
    private $buildTools;

    /**
     * @var \IPE\Installer\BuildOptions
     */
    private $buildOptions;

    public function __construct(PHPInfo $php, Filesystem $fs, BuildTools $buildTools, BuildOptions $buildOptions)
    {
        $this->php = $php;
        $this->fs = $fs;
        $this->buildTools = $buildTools;
        $this->buildOptions = $buildOptions;
    }

    /**
     * @throws \IPE\Exception\InvalidArgument if the extension is not available in binary form
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Installer\CompiledExtension|null NULL if the installer of the vendor already enabled the extension
     */
    public function install(Resolved $resolved)
    {
        $extension = $resolved->getKnownExtension();
        if ($extension === null) {
            throw new Exception\InvalidArgument("{$resolved} is not available in binary form");
        }
        $moduleName = $extension::getHandle();
        $modulePath = "{$this->php->getExtensionDir()}/{$moduleName}.so";
        $context = new Extension\BuildContext($resolved->getPath(), $resolved->getVersion(), $resolved->getOptionValues());
        if ($extension instanceof Extension\ProvidesBinaryInstaller) {
            $extension->runBinaryInstaller($context);
            $this->stripSymbols($modulePath, $resolved->getPath());

            return null;
        }
        if ($extension instanceof Extension\BuildsModule) {
            $source = $extension->buildModule($context, $this->buildTools, $this->buildOptions->getParallelJobs() ?: $this->buildTools->getProcessorCount());
            if ($source !== $modulePath) {
                $this->fs->copyFile($source, $modulePath);
            }
            $this->stripSymbols($modulePath, $resolved->getPath());

            return new CompiledExtension($resolved, $moduleName, $modulePath, false, $extension instanceof Extension\ProvidesIniSettings ? $extension->getIniSettings() : '');
        }
        if (!$extension instanceof Extension\ProvidesBinaryModule) {
            throw new Exception\InvalidArgument("{$resolved} is not available in binary form");
        }
        $source = $extension->findBinaryModule($resolved->getPath(), $resolved->getVersion());
        if ($source === '') {
            throw new Exception\IO("Failed to find the module of {$resolved}");
        }
        $this->fs->copyFile($source, $modulePath);
        $extension->postProcessBinaryModule($modulePath);
        $this->stripSymbols($modulePath, $resolved->getPath());

        return new CompiledExtension($resolved, $moduleName, $modulePath, $extension->isZendExtension(), $extension->getBinaryIniSettings($context));
    }

    /**
     * Extensions published with their debug symbols require the strip command: if it's missing, the others have nothing to remove.
     *
     * @param non-empty-string $modulePath
     * @param non-empty-string $workingDirectory
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function stripSymbols($modulePath, $workingDirectory)
    {
        if ($this->buildOptions->getType() === BuildOptions::TYPE_OPTIMIZED && $this->buildTools->getStrip() !== '' && $this->fs->isFile($modulePath)) {
            $this->buildTools->stripModule($modulePath, $workingDirectory);
        }
    }
}
