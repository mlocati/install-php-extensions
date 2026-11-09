<?php

namespace IPE\SystemDependencies;

use IPE\Exception;
use IPE\Filesystem\Command;
use IPE\Filesystem\Downloader;
use IPE\Installer\State;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

/**
 * Debian doesn't provide libc-client2007e anymore since version 13: we use the packages of Debian 12.
 */
final class libcclient extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-cclient';

    /**
     * @var string
     */
    const PACKAGE_DEV = 'libc-client2007e-dev';

    /**
     * @var \IPE\Filesystem\Downloader
     */
    private $downloader;

    /**
     * @var \Closure(): void|null
     */
    private $removeDevPackage;

    public function __construct(PHPInfo $php, OS\Posix $os, Values $options, Downloader $downloader)
    {
        parent::__construct($php, $os, $options);
        $this->downloader = $downloader;
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
        return [libopenssl::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        if ($this->os instanceof OS\Alpine) {
            return new Packages(
                ['c-client'],
                ['krb5-dev', 'imap-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if (libopenssl::isPHPUsingOpenSSL10OnStretch($this->php, $this->os)) {
                // The dependencies of libc-client2007e-dev, without libssl-dev (it conflicts with libssl1.0-dev)
                return new Packages(
                    ['libc-client2007e'],
                    ['libkrb5-dev', 'libpam0g-dev']
                );
            }
            $version = $this->os->getNumericVersion();
            if ($version <= OS\Debian::BOOKWORM) {
                return new Packages(
                    ['libc-client2007e'],
                    ['libkrb5-dev', 'libc-client-dev']
                );
            }
            if ($this->isInstalled()) {
                return new Packages(
                    ['sgml-base'],
                    ['libkrb5-dev']
                );
            }

            return new Packages(
                ['sgml-base'],
                ['libkrb5-dev', 'libpam0g-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            return new Packages(
                ['libc-client2007e'],
                ['libkrb5-dev', 'libc-client-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::install()
     */
    public function install(State $state)
    {
        if (!$this->os instanceof OS\Debian) {
            return;
        }
        if (libopenssl::isPHPUsingOpenSSL10OnStretch($this->php, $this->os)) {
            $this->installDevPackageWithoutLibssl($state);
        } elseif ($this->os->getNumericVersion() >= OS\Debian::TRIXIE) {
            $this->installPackagesOfBookworm($state);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::finalize()
     */
    public function finalize(State $state)
    {
        $removeDevPackage = $this->removeDevPackage;
        if ($removeDevPackage === null) {
            return;
        }
        $this->removeDevPackage = null;
        // At shutdown it would be too late: the volatile packages it depends on would have been removed
        $state->unregisterShutdownCallback($removeDevPackage);
        $removeDevPackage();
    }

    /**
     * Remove the development package at the finalization, or at the shutdown of the state if the installation fails.
     *
     * @return void
     */
    private function scheduleDevPackageRemoval(State $state)
    {
        $fs = $this->os->getFilesystem();
        $this->removeDevPackage = static function () use ($fs) {
            // Installed with dpkg, so it's not among the volatile packages
            $fs->run(Command::create('dpkg', ['-r', self::PACKAGE_DEV])->withOutput(Command::OUTPUT_SHOW));
        };
        $state->registerShutdownCallback($this->removeDevPackage);
    }

    /**
     * @return bool
     */
    private function isInstalled()
    {
        $fs = $this->os->getFilesystem();

        return $fs->isFile('/usr/lib/libc-client.so.2007e') && $fs->isFile('/usr/include/c-client/utf8.h');
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function installPackagesOfBookworm(State $state)
    {
        $fs = $this->os->getFilesystem();
        list($rc, $architecture) = $fs->run(Command::create('dpkg', ['--print-architecture'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        $architecture = trim($architecture);
        if ($rc !== 0 || $architecture === '') {
            throw new Exception\IO('Failed to determine the architecture of the system');
        }
        $debianVersion = $architecture === 'amd64' ? '2007f~dfsg-7+b2' : '2007f~dfsg-7+b1';
        if (!$fs->isFile('/usr/bin/mlock')) {
            $this->installPackage("mlock_{$debianVersion}_{$architecture}.deb", $state);
        }
        if (!$fs->isFile('/usr/lib/libc-client.so.2007e')) {
            $this->installPackage("libc-client2007e_{$debianVersion}_{$architecture}.deb", $state);
        }
        if (!$fs->isFile('/usr/include/c-client/utf8.h')) {
            $this->installPackage(self::PACKAGE_DEV . "_{$debianVersion}_{$architecture}.deb", $state);
            $this->scheduleDevPackageRemoval($state);
        }
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function installDevPackageWithoutLibssl(State $state)
    {
        $fs = $this->os->getFilesystem();
        $dir = $state->getVolatileDir()->createSubdir();
        list($rc) = $fs->run(Command::create('apt-get', ['download', self::PACKAGE_DEV])->withWorkingDirectory($dir)->withOutput(Command::OUTPUT_SHOW));
        $files = $rc === 0 ? $fs->listDirectoryContents($dir) : [];
        if (count($files) !== 1) {
            throw new Exception\IO('Failed to download ' . self::PACKAGE_DEV);
        }
        list($rc) = $fs->run(Command::create('dpkg', ['-i', '--ignore-depends=libssl-dev', "{$dir}/{$files[0]}"])->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\IO('Failed to install ' . self::PACKAGE_DEV);
        }
        $this->scheduleDevPackageRemoval($state);
    }

    /**
     * @param non-empty-string $filename
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function installPackage($filename, State $state)
    {
        $file = $state->getVolatileDir()->createSubdir() . "/{$filename}";
        $this->downloader->saveAs("http://ftp.debian.org/debian/pool/main/u/uw-imap/{$filename}", $file);
        list($rc) = $this->os->getFilesystem()->run(Command::create('dpkg', ['-i', $file])->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\IO("Failed to install {$filename}");
        }
    }
}
