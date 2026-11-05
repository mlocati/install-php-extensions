<?php

namespace IPE\SystemDependencies;

use IPE\Exception;
use IPE\Filesystem\ArchiveExtractor;
use IPE\Filesystem\ArchiveFormat;
use IPE\Filesystem\Command;
use IPE\Filesystem\Downloader;
use IPE\Installer\State;
use IPE\Option\CustomOption;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

/**
 * The Oracle Instant Client: it's not available in the repositories of the operating systems, so we can install it only after the system packages.
 *
 * @see https://www.oracle.com/database/technologies/instant-client.html
 */
final class oracleinstantclient extends SystemDependency
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @public
     * @var string
     */
    const HANDLE = 'oracle-instantclient';

    /**
     * @public
     * @var string
     */
    const OPTION_BASIC = 'basic';

    /**
     * @var \IPE\Filesystem\Downloader
     */
    private $downloader;

    /**
     * @var \IPE\Filesystem\ArchiveExtractor
     */
    private $archiveExtractor;

    /**
     * @var bool
     */
    private $sdkLinked = false;

    public function __construct(PHPInfo $php, OS\Posix $os, Values $options, Downloader $downloader, ArchiveExtractor $archiveExtractor, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        parent::__construct($php, $os, $options);
        $this->downloader = $downloader;
        $this->archiveExtractor = $archiveExtractor;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getOptions()
     */
    public static function getOptions()
    {
        return [
            new CustomOption(self::OPTION_BASIC, 'Install the Basic package instead of the much smaller Basic Light one', false),
        ];
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedOS
     *
     * @return non-empty-string
     */
    public static function getLibraryPath(PHPInfo $php, OS\Posix $os)
    {
        list($version, $client) = self::getRelease($php, $os);

        return "/usr/lib/oracle/{$version}/{$client}/lib";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getHandle()
     */
    public static function getHandle()
    {
        return self::HANDLE;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getRequiredDependencies()
     */
    public function getRequiredDependencies()
    {
        return [unzip::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        if ($this->os instanceof OS\Alpine) {
            return new Packages(['libaio', 'libc6-compat', 'libnsl']);
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::VERSION_BOOKWORM) {
                return new Packages(['^libaio[0-9]*$']);
            }

            return new Packages(['libaio1t64']);
        }

        throw new Exception\UnsupportedOS("The Oracle Instant Client can't be installed on {$this->os->getDisplayName()}");
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::install()
     */
    public function install(State $state)
    {
        $fs = $this->os->getFilesystem();
        if ($this->os instanceof OS\Debian && $this->os->getNumericVersion() >= OS\Debian::VERSION_TRIXIE) {
            $this->linkDebianLibaio();
        }
        $libraryPath = self::getLibraryPath($this->php, $this->os);
        if (!$fs->exists($libraryPath)) {
            $parent = dirname($libraryPath);
            if (!$fs->isDirectory($parent)) {
                $fs->createDirectory($parent);
            }
            // PHP can't rename directories across different devices
            list($rc, $output) = $fs->run(Command::create('mv', [$this->download($this->options->hasCustomValue(self::OPTION_BASIC) ? 'basic' : 'basiclite', $state), $libraryPath]));
            if ($rc !== 0) {
                throw new Exception\IO("Failed to install the Oracle Instant Client:\n{$output}");
            }
        }
        $sdkPath = "{$libraryPath}/sdk";
        if (!$fs->exists($sdkPath) && !$fs->isSymlink($sdkPath)) {
            $fs->createSymlink($this->download('sdk', $state) . '/sdk', $sdkPath);
            $this->sdkLinked = true;
        }
        if ($this->os instanceof OS\Alpine) {
            $this->configureAlpineLoader($libraryPath);
        } elseif (!$fs->exists('/etc/ld.so.conf.d/oracle-instantclient.conf')) {
            $fs->writeFile('/etc/ld.so.conf.d/oracle-instantclient.conf', "{$libraryPath}\n");
            $fs->run(Command::create('ldconfig')->withOutput(Command::OUTPUT_DISCARD));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::finalize()
     */
    public function finalize(State $state)
    {
        if ($this->sdkLinked) {
            // The SDK is needed only to compile: it's in the volatile directory
            $this->os->getFilesystem()->deleteFile(self::getLibraryPath($this->php, $this->os) . '/sdk');
            $this->sdkLinked = false;
        }
    }

    /**
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     *
     * @return array{non-empty-string, non-empty-string, non-empty-string} the version, the name of its directory, and the template of the URLs of its packages
     */
    private static function getRelease(PHPInfo $php, OS\Posix $os)
    {
        $base = 'https://download.oracle.com/otn_software/linux/instantclient/';
        switch (OS\Architecture::ofPHP($php, $os)) {
            case OS\Architecture::X86:
                return ['19.9', 'client', "{$base}199000/instantclient-%s-linux-19.9.0.0.0dbru.zip"];
            case OS\Architecture::X64:
                return ['21.1', 'client64', "{$base}211000/instantclient-%s-linux.x64-21.1.0.0.0.zip"];
            case OS\Architecture::ARM64:
                return ['19.10', 'client64', "{$base}191000/instantclient-%s-linux.arm64-19.10.0.0.0dbru.zip"];
        }

        throw new Exception\UnsupportedOS('The Oracle Instant Client is not available for ' . OS\Architecture::describe($php, $os));
    }

    /**
     * @param non-empty-string $package
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     * @throws \IPE\Exception\UnsupportedOS
     *
     * @return non-empty-string the root directory of the extracted package
     */
    private function download($package, State $state)
    {
        list(, , $urlTemplate) = self::getRelease($this->php, $this->os);
        $this->logger->info("Installing the package {$package} of the Oracle Instant Client");
        $archive = $state->getVolatileDir()->createSubdir() . '/instantclient.zip';
        $this->downloader->saveAs(sprintf($urlTemplate, $package), $archive);
        $rootDir = $this->archiveExtractor->extractTo($archive, $state->getVolatileDir()->createSubdir(), ArchiveFormat::ZIP);
        $this->os->getFilesystem()->deleteFile($archive);

        return $rootDir;
    }

    /**
     * The client looks for libaio.so.1, but since Debian 13 the library is named libaio.so.1t64.
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function linkDebianLibaio()
    {
        $fs = $this->os->getFilesystem();
        list(, $multiarch) = $fs->run(Command::create('gcc', ['-print-multiarch'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        $directory = '/usr/lib/' . trim($multiarch);
        if ($fs->isFile("{$directory}/libaio.so.1t64") && !$fs->isFile("{$directory}/libaio.so.1")) {
            $fs->createSymlink("{$directory}/libaio.so.1t64", "{$directory}/libaio.so.1");
        }
        if ($fs->isFile("{$directory}/libaio.so.1") && !$fs->isFile("{$directory}/libaio.so")) {
            $fs->createSymlink("{$directory}/libaio.so.1", "{$directory}/libaio.so");
        }
    }

    /**
     * @param non-empty-string $libraryPath
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function configureAlpineLoader($libraryPath)
    {
        $fs = $this->os->getFilesystem();
        if (!$fs->exists('/usr/lib/libresolv.so.2') && $fs->exists('/lib/libc.so.6')) {
            $fs->createSymlink('/lib/libc.so.6', '/usr/lib/libresolv.so.2');
        }
        $pathFile = $this->getMuslPathFile();
        if (!$fs->exists($pathFile)) {
            $default = $this->php->getBits() === 32 ? '/lib:/usr/local/lib:/usr/lib' : '/lib64:/lib:/usr/local/lib:/usr/lib';
            $fs->writeFile($pathFile, "{$default}:{$libraryPath}\n");

            return;
        }
        $content = $fs->readFile($pathFile);
        if (strpos($content, $libraryPath) === false) {
            $lines = explode("\n", $content, 2);
            $lines[0] .= ":{$libraryPath}";
            $fs->writeFile($pathFile, implode("\n", $lines));
        }
    }

    /**
     * The name of the file comes from the one of the dynamic loader, which may differ from the machine architecture (eg i386 for i586).
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    private function getMuslPathFile()
    {
        $matches = null;
        foreach ($this->os->getFilesystem()->listDirectoryContents('/lib') as $item) {
            if (preg_match('/^ld-musl-(.+)\.so\.1$/D', basename($item), $matches)) {
                return "/etc/ld-musl-{$matches[1]}.path";
            }
        }

        throw new Exception\IO('Unable to find the dynamic loader of musl');
    }
}
