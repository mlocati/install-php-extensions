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
use IPE\SystemDependencies;

/**
 * @see https://www.saxonica.com/download/c.xml
 */
final class saxon extends Extension implements
    Extension\CompiledFromVendorArchive,
    Extension\DeclaresUnsupportedEnvironments,
    Extension\DeclaresDefaultVersion,
    Extension\DeclaresOptions,
    Extension\RequiresSystemDependencies
{
    /**
     * @var \IPE\Filesystem\Downloader
     */
    protected $downloader;

    public function __construct(PHPInfo $php, OS $os, Downloader $downloader)
    {
        parent::__construct($php, $os);
        $this->downloader = $downloader;
    }

    /**
     * @public
     * @var string
     */
    const HANDLE = 'saxon';

    /**
     * @public
     * @var string
     */
    const OPTION_EDITION = 'edition';

    /**
     * The source of the download page (which is rendered with JavaScript).
     *
     * @public
     * @var string
     */
    const DOWNLOAD_PAGE = 'https://www.saxonica.com/src/download/c.xml';

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
            'alpine-php7' => 'the source code of SaxonC does not compile (int64_t is not defined)',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return [
            'saxon',
            'saxonc',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        return $this->php->getMajorMinorVersion() < 800 ? '12.3' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresOptions::getOptions()
     */
    public static function getOptions()
    {
        return [
            new Option\CustomOption(self::OPTION_EDITION, 'The edition to be installed: EE (Enterprise), PE (Professional) or HE (Home)', true, 'EE'),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\libstdcxx::HANDLE, SystemDependencies\unzip::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::getVendorArchiveVersion()
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\IO\NotFound
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\UnsupportedExtension
     * @throws \IPE\Exception\UnsupportedOS
     */
    public function getVendorArchiveVersion($versionRequirements, Option\Values $options)
    {
        if ($versionRequirements !== '') {
            if (!preg_match('/^\d+(\.\d+)*$/D', $versionRequirements)) {
                throw new Exception\UnsupportedExtension('The extension ' . self::getHandle() . " can only be installed by specifying an exact version number (you asked for '{$versionRequirements}')");
            }

            return $versionRequirements;
        }
        $edition = self::getEdition($options);
        $architecture = $this->getLinuxArchitectureName([
            OS\Architecture::X64 => 'x86_64',
            OS\Architecture::ARM64 => '(?:arm64|aarch64)',
        ]);
        $page = $this->downloader->getContent(self::DOWNLOAD_PAGE);
        $matches = null;
        preg_match_all("#/SaxonC{$edition}-linux-{$architecture}-(\\d+(?:-\\d+)+)\\.zip\\b#", $page, $matches);
        $latest = '';
        foreach ($matches[1] as $match) {
            $version = str_replace('-', '.', $match);
            if ($latest === '' || version_compare($version, $latest) > 0) {
                $latest = $version;
            }
        }
        if ($latest === '') {
            if (strpos($page, "/SaxonC{$edition}-linux-") !== false) {
                throw new Exception\IO\NotFound("SaxonC is not available for {$architecture}");
            }
            throw new Exception\IO('Failed to determine the latest version of SaxonC from ' . self::DOWNLOAD_PAGE);
        }

        return $latest;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::getVendorArchiveURL()
     */
    public function getVendorArchiveURL($version, Option\Values $options)
    {
        $edition = self::getEdition($options);
        $architecture = $this->getLinuxArchitectureName([
            OS\Architecture::X64 => 'x86_64',
            OS\Architecture::ARM64 => version_compare($version, '12.6') >= 0 && version_compare($version, '12.9') < 0 ? 'aarch64' : 'arm64',
        ]);
        $base = 'https://downloads.saxonica.com/SaxonC/' . $edition . '/' . strtok($version, '.') . '/';
        if (version_compare($version, '12.6') >= 0) {
            return "{$base}SaxonC{$edition}-linux-{$architecture}-" . str_replace('.', '-', $version) . '.zip';
        }
        // The name of the old archives for x86_64 doesn't contain the architecture
        if (version_compare($version, '12.0') < 0 && $architecture === 'x86_64') {
            return "{$base}libsaxon-{$edition}C-setup64-v{$version}.zip";
        }

        return "{$base}libsaxon-{$edition}C-linux-{$architecture}-v{$version}.zip";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::prepareVendorSource()
     */
    public function prepareVendorSource($rootDir, $version, Option\Values $options)
    {
        $fs = $this->os->getFilesystem();
        // The -P option is here because the archives contain the symbolic links of the shared libraries
        self::runFind($fs, $rootDir, ['(', '-type', 'f', '-o', '-type', 'l', ')', '(', '-name', 'libsaxonc-*.so*', '-o', '-name', 'libsaxon-*.so*', ')', '-exec', 'cp', '-P', '{}', '/usr/lib/', ';']);
        // Some systems don't have ldconfig, and they don't need it
        $fs->run(Command::create('ldconfig', []));
        if (version_compare($version, '13.0.0') < 0) {
            self::runFind($fs, $rootDir, ['-type', 'f', '-name', 'php8_XsltExecutable.cpp', '-exec', 'sed', '-i', 's/Z_TYPE_P(val) != NULL/Z_TYPE_P(val) != IS_NULL/g', '{}', ';']);
        }
        $sourceDir = $fs->isDirectory("{$rootDir}/php/src") ? "{$rootDir}/php/src" : "{$rootDir}/Saxon.C.API";
        if (version_compare($version, '13.0.0') === 0) {
            // See https://saxonica.plan.io/issues/7113
            $configM4 = "{$sourceDir}/config.m4";
            $fs->writeFile($configM4, (string) preg_replace('/^ *EXTENSION_DIR=.*/m', '', str_replace('../../../src/php8_saxon.cpp', 'php8_saxon.cpp', $fs->readFile($configM4))));
        }

        return $sourceDir;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::getVendorConfigureArguments()
     */
    public function getVendorConfigureArguments($rootDir, $version, Option\Values $options)
    {
        $edition = self::getEdition($options);
        if (version_compare($version, '13.0.0') >= 0) {
            return ["--with-saxonc={$rootDir}/SaxonC{$edition}", "--with-saxonc-core={$rootDir}/SaxonCCore{$edition}"];
        }

        return ["--with-saxon={$rootDir}/SaxonC{$edition}"];
    }

    /**
     * @throws \IPE\Exception\InvalidOptionValues
     *
     * @return non-empty-string
     */
    private static function getEdition(Option\Values $options)
    {
        $edition = $options->getCustomValue(self::OPTION_EDITION, 'EE');
        if (!in_array($edition, ['EE', 'PE', 'HE'], true)) {
            throw new Exception\InvalidOptionValues("Invalid value of the option '" . self::OPTION_EDITION . "' of the extension " . self::HANDLE . ': it must be EE, PE or HE');
        }

        return $edition;
    }

    /**
     * @param array<string, non-empty-string> $names
     *
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    private function getLinuxArchitectureName(array $names)
    {
        if (!$this->os instanceof OS\Posix) {
            throw new Exception\UnsupportedOS("SaxonC can't be installed on {$this->os->getDisplayName()}");
        }

        return OS\Architecture::getVendorName($this->php, $this->os, $names);
    }

    /**
     * @param non-empty-string $directory
     * @param list<string> $expression
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private static function runFind(Filesystem $fs, $directory, array $expression)
    {
        list($rc, $output) = $fs->run(Command::create('find', array_merge([$directory], $expression)));
        if ($rc !== 0) {
            throw new Exception\IO("Failed to prepare the SaxonC archive:\n{$output}");
        }
    }
}
