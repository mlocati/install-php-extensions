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
 * The Microsoft ODBC Driver for SQL Server: it's not available in the repositories of the operating systems, so we can install it only after the system packages.
 *
 * @see https://learn.microsoft.com/en-us/sql/connect/odbc/linux-mac/installing-the-microsoft-odbc-driver-for-sql-server
 */
final class msodbcsql extends SystemDependency
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @public
     * @var string
     */
    const HANDLE = 'msodbcsql';

    /**
     * The version of the APK package of the driver 18, and the path where Microsoft publishes it.
     *
     * @public
     * @var string
     */
    const APK_VERSION_18 = '18.7.1.1-1@ade174b7-8cea-4543-91a6-c33ae320c2f0';

    /**
     * The version of the APK package of the driver 17, and the path where Microsoft publishes it.
     *
     * @public
     * @var string
     */
    const APK_VERSION_17 = '17.11.1.1-1@607ebe2c-e17c-4c34-b367-10a75b83bef9';

    /**
     * The newest version of the driver 18 that doesn't require libstdc++ 11, which is available since Alpine 3.16.
     *
     * @public
     * @var string
     */
    const APK_VERSION_18_OLD_LIBSTDCPP = '18.3.3.1-1@3/5/5/355d7943-a338-41a7-858d-53b259ea33f5';

    /**
     * The newest version of the driver 17 that doesn't require libstdc++ 11, which is available since Alpine 3.16.
     *
     * @public
     * @var string
     */
    const APK_VERSION_17_OLD_LIBSTDCPP = '17.10.6.1-1@e/4/e/e4e67866-dffd-428c-aac7-8d28ddafb39b';

    /**
     * @var \IPE\Filesystem\Downloader
     */
    private $downloader;

    public function __construct(PHPInfo $php, OS\Posix $os, Values $options, Downloader $downloader, \IPE\Logger $logger)
    {
        $this->logger = $logger;
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
     * @see \IPE\SystemDependency::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        if ($this->os instanceof OS\Alpine) {
            // The PHP extensions that use the driver are written in C++
            return new Packages(
                ['libstdc++', 'unixodbc'],
                ['unixodbc-dev']
            );
        }
        // The driver uses libgssapi-krb5-2 without declaring it, and the build dependencies may pull it in: it must not be removed with them
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::STRETCH) {
                return new Packages(
                    // APT supports HTTPS by itself since Debian 10: before that, apt-get update would always fail without this package since the Microsoft repository stays configured
                    ['unixodbc', 'libgssapi-krb5-2', 'apt-transport-https'],
                    ['unixodbc-dev']
                );
            }

            return new Packages(
                ['unixodbc', 'libgssapi-krb5-2'],
                ['unixodbc-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::XENIAL) {
                return new Packages(
                    // APT supports HTTPS by itself since Ubuntu 18.04
                    ['unixodbc', 'libgssapi-krb5-2', 'apt-transport-https'],
                    ['unixodbc-dev']
                );
            }

            return new Packages(
                ['unixodbc', 'libgssapi-krb5-2'],
                ['unixodbc-dev']
            );
        }

        throw new Exception\UnsupportedOS("The Microsoft ODBC Driver for SQL Server can't be installed on {$this->os->getDisplayName()}");
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::install()
     */
    public function install(State $state)
    {
        if ($this->isInstalled()) {
            return;
        }
        $this->logger->info('Installing the Microsoft ODBC Driver for SQL Server');
        if ($this->os instanceof OS\Alpine) {
            $this->installOnAlpine($state);

            return;
        }
        if ($this->os instanceof OS\Debian) {
            $this->installOnDebian($this->os, $state);

            return;
        }
        if ($this->os instanceof OS\Ubuntu) {
            $this->installOnUbuntu($this->os, $state);

            return;
        }

        throw new Exception\UnsupportedOS("The Microsoft ODBC Driver for SQL Server can't be installed on {$this->os->getDisplayName()}");
    }

    /**
     * The driver registers itself in the configuration of unixODBC.
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    private function isInstalled()
    {
        $fs = $this->os->getFilesystem();

        return $fs->isFile('/etc/odbcinst.ini') && preg_match('/^\[ODBC Driver \d+ for SQL Server\]/m', $fs->readFile('/etc/odbcinst.ini')) === 1;
    }

    /**
     * The driver 18 doesn't work with the versions of the PHP extensions for PHP 7.3 and earlier.
     *
     * @return bool
     */
    private function needsDriver17()
    {
        return $this->php->getMajorMinorVersion() <= 703;
    }

    /**
     * @return non-empty-string
     */
    private function getAlpinePackage()
    {
        if ($this->os->getNumericVersion() < 316) {
            return $this->needsDriver17() ? self::APK_VERSION_17_OLD_LIBSTDCPP : self::APK_VERSION_18_OLD_LIBSTDCPP;
        }

        return $this->needsDriver17() ? self::APK_VERSION_17 : self::APK_VERSION_18;
    }

    /**
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return void
     */
    private function installOnAlpine(State $state)
    {
        list($version, $path) = explode('@', $this->getAlpinePackage());
        $architecture = OS\Architecture::getVendorName($this->php, $this->os, [
            OS\Architecture::X64 => 'amd64',
            OS\Architecture::ARM64 => 'arm64',
        ]);
        $major = strtok($version, '.');
        $file = $state->getVolatileDir()->createSubdir() . '/msodbcsql.apk';
        try {
            $this->downloader->saveAs("https://download.microsoft.com/download/{$path}/msodbcsql{$major}_{$version}_{$architecture}.apk", $file);
        } catch (Exception\IO\NotFound $_) {
            throw new Exception\UnsupportedOS("The Microsoft ODBC Driver {$major} for SQL Server is not available for " . OS\Architecture::describe($this->php, $this->os));
        }
        list($rc) = $this->os->getFilesystem()->run(Command::create('apk', ['add', '--allow-untrusted', $file])->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('Failed to install the Microsoft ODBC Driver for SQL Server');
        }
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return void
     */
    private function installOnDebian(OS\Debian $os, State $state)
    {
        $version = $os->getNumericVersion();
        $this->installFromMicrosoftRepository(
            $os,
            "debian/{$version}/prod {$os->getCodename()}",
            $version >= OS\Debian::TRIXIE ? 'microsoft-2025.asc' : 'microsoft.asc',
            // The APT of Debian 8 doesn't support signed-by
            $version >= OS\Debian::STRETCH,
            $this->getDebianPackage($os),
            $state
        );
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return void
     */
    private function installOnUbuntu(OS\Ubuntu $os, State $state)
    {
        $version = $os->getNumericVersion();
        $this->installFromMicrosoftRepository(
            $os,
            sprintf('ubuntu/%d.%02d/prod %s', (int) floor($version / 100), $version % 100, $os->getCodename()),
            $version >= OS\Ubuntu::QUESTING ? 'microsoft-2025.asc' : 'microsoft.asc',
            // The APT of Ubuntu 16.04 doesn't support keys in .asc format
            $version >= OS\Ubuntu::BIONIC,
            $this->getUbuntuPackage($os),
            $state
        );
    }

    /**
     * @param non-empty-string $repository the path of the repository followed by the distribution (eg 'debian/13/prod trixie')
     * @param non-empty-string $keyFile
     * @param bool $useSignedBy
     * @param non-empty-string $package
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return void
     */
    private function installFromMicrosoftRepository(OS\Posix $os, $repository, $keyFile, $useSignedBy, $package, State $state)
    {
        $fs = $os->getFilesystem();
        $sourceList = '/etc/apt/sources.list.d/mssql-release.list';
        if (!$fs->isFile($sourceList)) {
            $options = $this->installAptKey($os, "https://packages.microsoft.com/keys/{$keyFile}", $useSignedBy, $state);
            $fs->writeFile($sourceList, "deb {$options}https://packages.microsoft.com/{$repository} main\n");
            $os->getSystemPackager()->refresh();
        }
        // The postinst script of the package refuses to continue if the license is not accepted
        list($rc) = $fs->run(Command::create('apt-get', ['install', '-qy', '--no-install-recommends', $package])->withEnvironment(['ACCEPT_EULA' => 'Y'])->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('Failed to install the Microsoft ODBC Driver for SQL Server');
        }
    }

    /**
     * @param non-empty-string $url
     * @param bool $useSignedBy
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return string the options for the line of the APT source list
     */
    private function installAptKey(OS\Posix $os, $url, $useSignedBy, State $state)
    {
        $fs = $os->getFilesystem();
        if ($useSignedBy) {
            // A key in .asc format doesn't require gnupg
            if (!$fs->isDirectory('/usr/share/keyrings')) {
                $fs->createDirectory('/usr/share/keyrings');
            }
            $this->downloader->saveAs($url, '/usr/share/keyrings/microsoft-prod.asc');

            return '[signed-by=/usr/share/keyrings/microsoft-prod.asc] ';
        }
        $file = $state->getVolatileDir()->createSubdir() . '/microsoft.asc';
        $this->downloader->saveAs($url, $file);
        list($rc) = $fs->run(Command::create('apt-key', ['add', $file])->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('Failed to install the APT key of Microsoft');
        }

        return '';
    }

    /**
     * @return non-empty-string
     */
    private function getDebianPackage(OS\Debian $os)
    {
        if ($this->needsDriver17()) {
            return 'msodbcsql17';
        }

        // Up to Debian 13 both the drivers 17 and 18 are available: let's install just one
        return $os->getNumericVersion() <= OS\Debian::TRIXIE ? 'msodbcsql18' : '^msodbcsql[0-9]+$';
    }

    /**
     * @return non-empty-string
     */
    private function getUbuntuPackage(OS\Ubuntu $os)
    {
        // Microsoft doesn't publish the driver 18 for Ubuntu 16.04 and earlier
        if ($this->needsDriver17() || $os->getNumericVersion() <= OS\Ubuntu::XENIAL) {
            return 'msodbcsql17';
        }

        return 'msodbcsql18';
    }
}
