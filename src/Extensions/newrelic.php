<?php

namespace IPE\Extensions;

use IPE\Exception;
use IPE\Extension;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Filesystem\Downloader;
use IPE\Option;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\binutils;

/**
 * @see https://docs.newrelic.com/docs/apm/agents/php-agent/getting-started/php-agent-compatibility-requirements/
 */
final class newrelic extends Extension implements
    Extension\ProvidesBinaryInstaller,
    Extension\DeclaresDefaultVersion,
    Extension\DeclaresOptions,
    Extension\DeclaresThreadSafetySupport,
    Extension\RequiresSystemDependencies
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var \IPE\Filesystem\Downloader
     */
    protected $downloader;

    public function __construct(PHPInfo $php, OS $os, Downloader $downloader, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        parent::__construct($php, $os);
        $this->downloader = $downloader;
    }

    /**
     * @public
     * @var string
     */
    const HANDLE = 'newrelic';

    /**
     * @public
     * @var string
     */
    const OPTION_INSTALL_DAEMON = 'install-daemon';

    /**
     * @public
     * @var string
     */
    const OPTION_KEEP_LOG = 'keep-log';

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
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return [
            'newrelic',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresThreadSafetySupport::supportsZTS()
     */
    public function supportsZTS()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresThreadSafetySupport::supportsNTS()
     */
    public function supportsNTS()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        return $this->php->getMajorMinorVersion() <= 701 ? '10.21.0.11' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresOptions::getOptions()
     */
    public static function getOptions()
    {
        return [
            new Option\CustomOption(self::OPTION_INSTALL_DAEMON, 'Also install the New Relic daemon', false),
            new Option\CustomOption(self::OPTION_KEEP_LOG, 'Keep the log file of the New Relic installer', false),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        // The module is published with its debug symbols
        return [binutils::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableAsBinary::getBinaryArchiveURL()
     */
    public function getBinaryArchiveURL($versionRequirements)
    {
        if ($versionRequirements !== '' && !preg_match('/^\d+(\.\d+)*$/D', $versionRequirements)) {
            throw new Exception\UnsupportedExtension("The extension newrelic can only be installed by specifying an exact version number (you asked for '{$versionRequirements}')");
        }
        if ($this->os instanceof OS\Alpine) {
            $platform = 'linux-musl';
        } elseif ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            $platform = 'linux';
        } else {
            throw new Exception\UnsupportedOS("New Relic can't be installed on {$this->os->getDisplayName()}");
        }
        $baseURL = 'https://download.newrelic.com/php_agent/' . ($versionRequirements === '' ? 'release/' : "archive/{$versionRequirements}/");
        // The file name contains the version, and it's listed only in this page
        $matches = null;
        if (!preg_match_all('/\bnewrelic-php[0-9.]*-[0-9]+(\.[0-9]+)*-' . preg_quote($platform, '/') . '\.tar\.gz/', strip_tags($this->downloader->getContent($baseURL)), $matches)) {
            throw new Exception\IO("Failed to find the New Relic archive for {$platform} in {$baseURL}");
        }
        $files = $matches[0];
        sort($files);

        return $baseURL . $files[0];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryInstaller::canRunBinaryInstaller()
     */
    public function canRunBinaryInstaller($rootDir)
    {
        // The available architectures change from version to version
        $architecture = OS\Architecture::getVendorName($this->php, $this->os, [
            OS\Architecture::X86 => 'x86',
            OS\Architecture::X64 => 'x64',
            OS\Architecture::ARM64 => 'aarch64',
        ]);

        return $this->os->getFilesystem()->isDirectory("{$rootDir}/agent/{$architecture}");
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesBinaryInstaller::runBinaryInstaller()
     */
    public function runBinaryInstaller(Extension\BuildContext $context)
    {
        $rootDir = $context->getSourceDirectory();
        $options = $context->getOptionValues();
        $fs = $this->os->getFilesystem();
        self::runInstaller($fs, $rootDir, 'install');
        if ($options->hasCustomValue(self::OPTION_INSTALL_DAEMON)) {
            self::runInstaller($fs, $rootDir, 'install_daemon');
        }
        if (!$options->hasCustomValue(self::OPTION_KEEP_LOG)) {
            foreach ($fs->listDirectoryContents('/tmp') as $item) {
                if (preg_match('/^nrinstall-.*\.tar$/D', basename($item))) {
                    $fs->deleteFile('/tmp/' . basename($item));
                }
            }
        }
        $this->logger->notice("You may need to change the owner/permissions of /var/log/newrelic, and to set the value of the newrelic.license configuration key (if you didn't set the NR_INSTALL_KEY environment variable)");
    }

    /**
     * @param non-empty-string $rootDir
     * @param non-empty-string $action
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private static function runInstaller(Filesystem $fs, $rootDir, $action)
    {
        $command = Command::create('./newrelic-install', [$action])
            ->withWorkingDirectory($rootDir)
            ->withEnvironment(['NR_INSTALL_USE_CP_NOT_LN' => '1', 'NR_INSTALL_SILENT' => '1'])
            ->withOutput(Command::OUTPUT_SHOW)
        ;
        list($rc) = $fs->run($command);
        if ($rc !== 0) {
            throw new Exception\IO("The New Relic installer failed with exit code {$rc}");
        }
    }
}
