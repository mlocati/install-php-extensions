<?php

namespace IPE\Extensions;

use IPE\Exception;
use IPE\Extension;
use IPE\Filesystem\Command;
use IPE\Filesystem\Downloader;
use IPE\Option;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\PHPScript;
use IPE\SystemDependencies;

/**
 * @see https://github.com/DataDog/dd-trace-php
 */
final class ddtrace extends Extension implements
    Extension\ProvidesBinaryInstaller,
    Extension\DeclaresConflicts,
    Extension\DeclaresOptions,
    Extension\DeclaresUnsupportedEnvironments,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'ddtrace';

    /**
     * @public
     * @var string
     */
    const OPTION_APPSEC = 'enable-appsec';

    /**
     * @public
     * @var string
     */
    const OPTION_PROFILING = 'enable-profiling';

    /**
     * @var \IPE\Filesystem\Downloader
     */
    private $downloader;

    /**
     * @var \IPE\Service\PHPScript
     */
    private $phpScript;

    public function __construct(PHPInfo $php, OS $os, Downloader $downloader, PHPScript $phpScript)
    {
        parent::__construct($php, $os);
        $this->downloader = $downloader;
        $this->phpScript = $phpScript;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return self::HANDLE;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getSupportedPHPVersions()
     */
    public static function getSupportedPHPVersions()
    {
        return ['7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresUnsupportedEnvironments::getUnsupportedEnvironments()
     */
    public static function getUnsupportedEnvironments()
    {
        return [
            'debian8' => "the extension can't be installed on this system",
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['ddtrace'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresConflicts::getConflictingExtensions()
     */
    public function getConflictingExtensions($version)
    {
        // ddtrace disables itself with any Xdebug on PHP 7.0 (with newer PHP versions only with Xdebug < 2.9.5, which xdebug declares)
        return $this->php->getMajorMinorVersion() <= 700 ? [xdebug::HANDLE] : [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresOptions::getOptions()
     */
    public static function getOptions()
    {
        return [
            new Option\CustomOption(self::OPTION_APPSEC, 'Enable the application security module', false),
            new Option\CustomOption(self::OPTION_PROFILING, 'Enable the profiling module', false),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\libstdcxx::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsBinary::getBinaryArchiveURL()
     */
    public function getBinaryArchiveURL($versionRequirements)
    {
        if ($versionRequirements !== '' && !preg_match('/^\d+(\.\d+)*$/D', $versionRequirements)) {
            throw new Exception\UnsupportedExtension("The extension ddtrace can only be installed by specifying an exact version number (you asked for '{$versionRequirements}')");
        }

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryInstaller::canRunBinaryInstaller()
     */
    public function canRunBinaryInstaller($rootDir)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryInstaller::runBinaryInstaller()
     */
    public function runBinaryInstaller(Extension\BuildContext $context)
    {
        $version = $context->getVersion();
        $options = $context->getOptionValues();
        $base = 'https://github.com/DataDog/dd-trace-php/releases/';
        $url = $base . ($version === '' ? 'latest/download' : "download/{$version}") . '/datadog-setup.php';
        $setup = "{$context->getSourceDirectory()}/datadog-setup.php";
        $this->downloader->saveAs($url, $setup);
        $arguments = ['--php-bin=php'];
        if ($options->hasCustomValue(self::OPTION_APPSEC)) {
            $arguments[] = '--enable-appsec';
        }
        if ($options->hasCustomValue(self::OPTION_PROFILING)) {
            $arguments[] = '--enable-profiling';
        }
        list($rc) = $this->os->getFilesystem()->run($this->phpScript->createCommand($setup, $arguments)->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\IO("The installer of ddtrace failed with exit code {$rc}");
        }
    }
}
