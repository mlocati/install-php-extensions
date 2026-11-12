<?php

namespace IPE\Dev;

use IPE\Exception;
use IPE\Extensions;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\Service\PHPSystemPackage;
use IPE\SystemPackager;

/**
 * The extensions that take long to be compiled: the CI tests them in their own jobs.
 *
 * @internal
 */
final class SlowExtensions
{
    /**
     * @return non-empty-list<non-empty-string>
     */
    public static function getHandles()
    {
        return array_map(
            static function ($class) {
                return $class::getHandle();
            },
            self::getClasses()
        );
    }

    /**
     * Where the slow extensions are available as system packages: elsewhere they are compiled even without --system-packages=never.
     * Keys: the handles of the extensions, then the operating systems of the images with PHP installed by the system packager.
     * Values: true for all the PHP versions of the images (among the ones supported by the extension), or the list of the PHP versions.
     * Verified by check().
     *
     * @return array<non-empty-string, array<non-empty-string, true|non-empty-list<non-empty-string>>>
     */
    public static function getSystemPackages()
    {
        return [
            Extensions\cassandra::HANDLE => [
                'almalinux9-remi' => true,
                'almalinux10-remi' => true,
            ],
            Extensions\grpc::HANDLE => [
                'alpine3.24-apk' => true,
                'trixie-sury' => true,
                'ubuntu24.04-sury' => true,
                'almalinux9-remi' => true,
                'almalinux10-remi' => true,
            ],
            Extensions\pdo_firebird::HANDLE => [
                'bookworm-apt' => true,
                'trixie-apt' => true,
                'trixie-sury' => true,
                'ubuntu22.04-apt' => true,
                'ubuntu24.04-apt' => true,
                'ubuntu24.04-sury' => true,
                'ubuntu26.04-apt' => true,
                'almalinux9-remi' => true,
                'almalinux10-remi' => true,
                'fedora44-dnf' => true,
                'fedora45-dnf' => true,
            ],
            Extensions\phalcon::HANDLE => [
                'trixie-sury' => ['8.1', '8.2', '8.3', '8.4', '8.5'],
                'ubuntu24.04-sury' => ['8.1', '8.2', '8.3', '8.4', '8.5'],
                'almalinux9-remi' => true,
                'almalinux10-remi' => true,
            ],
            Extensions\swoole::HANDLE => [
                'alpine3.24-apk' => true,
                'trixie-sury' => ['8.1', '8.2', '8.3', '8.4', '8.5'],
                'ubuntu24.04-sury' => ['8.2', '8.3', '8.4', '8.5'],
                'almalinux9-remi' => true,
                'almalinux10-remi' => true,
            ],
        ];
    }

    /**
     * @param string $extension an argument like "grpc" or "grpc:1.60.0"
     * @param string $os
     * @param string $phpVersion major.minor
     *
     * @return bool false if the extension is (or may be) available as a system package
     */
    public static function isWithoutSystemPackage($extension, $os, $phpVersion)
    {
        $systemPackages = self::getSystemPackages();
        $handle = explode(':', $extension, 2)[0];
        if (!isset($systemPackages[$handle])) {
            return false;
        }
        if (!isset($systemPackages[$handle][$os])) {
            return true;
        }
        $phpVersions = $systemPackages[$handle][$os];

        return $phpVersions !== true && !in_array($phpVersion, $phpVersions, true);
    }

    /**
     * Inspect the package indexes, to get what getSystemPackages() should return.
     *
     * @throws \IPE\Exception\IO if a package index can't be downloaded
     * @throws \RuntimeException
     *
     * @return array<non-empty-string, array<non-empty-string, true|non-empty-list<non-empty-string>>>
     */
    public static function check()
    {
        $imagePHPVersions = [];
        foreach (DockerImages::getAll() as $image) {
            if ($image->getBaseImage() !== '') {
                $imagePHPVersions[$image->getOS()][] = $image->getPHPVersion();
            }
        }
        $packageIndexes = self::getPackageIndexes();
        $logger = new \IPE\Logger();
        $fs = new Filesystem\Local($logger);
        $downloader = (new Filesystem\Downloader\Factory($fs, $logger))->buildDownloader();
        $archiveExtractor = new Filesystem\ArchiveExtractor($fs, $logger);
        $volatileDirectory = $fs->createVolatileDirectory();
        $result = array_fill_keys(self::getHandles(), []);
        // Some images share the same package indexes
        $loadedIndexes = [];
        foreach (DockerImages::getOperatingSystems() as $os) {
            if (!isset($imagePHPVersions[$os])) {
                continue;
            }
            if (!isset($packageIndexes[$os])) {
                throw new \RuntimeException("The package indexes of {$os} are not defined");
            }
            $packager = $packageIndexes[$os]['packager'];
            $index = ['packages' => [], 'providers' => []];
            foreach ($packageIndexes[$os]['urls'] as $url) {
                if (!isset($loadedIndexes[$url])) {
                    switch ($packager) {
                        case 'apk':
                            $loadedIndexes[$url] = self::loadAPKIndex($url, $downloader, $archiveExtractor, $volatileDirectory);
                            break;
                        case 'apt':
                            $loadedIndexes[$url] = self::loadAPTIndex($url, $downloader, $volatileDirectory);
                            break;
                        default:
                            $loadedIndexes[$url] = self::loadRPMIndex($url, $downloader, $volatileDirectory);
                            break;
                    }
                }
                $index['packages'] += $loadedIndexes[$url]['packages'];
                $index['providers'] += $loadedIndexes[$url]['providers'];
            }
            switch ($packager) {
                case 'apk':
                    $osWithPackager = ExtensionFactory::createOSWithSystemPackager(OS\Alpine::class, SystemPackager\APK::class);
                    $phpSourceFormat = 'php{major}{minor}';
                    break;
                case 'apt':
                    $osWithPackager = ExtensionFactory::createOSWithSystemPackager(OS\Debian::class, SystemPackager\APT::class);
                    $phpSourceFormat = 'php{major}.{minor}';
                    break;
                default:
                    // The names of the packages don't depend on the version of dnf
                    $osWithPackager = ExtensionFactory::createOSWithSystemPackager(OS\AlmaLinux::class, SystemPackager\DNF\v4::class);
                    $phpSourceFormat = isset($packageIndexes[$os]['phpSource']) ? $packageIndexes[$os]['phpSource'] : 'php';
                    break;
            }
            foreach (self::getClasses() as $class) {
                $phpVersions = array_values(array_intersect($imagePHPVersions[$os], $class::getSupportedPHPVersions()));
                $withPackage = [];
                foreach ($phpVersions as $phpVersion) {
                    list($major, $minor) = explode('.', $phpVersion);
                    $phpSource = strtr($phpSourceFormat, ['{major}' => $major, '{minor}' => $minor]);
                    if ($phpSource === '') {
                        throw new \RuntimeException("Invalid phpSource for {$os}");
                    }
                    $phpPackage = new SystemPackager\Package($packager === 'apk' ? $phpSource : "{$phpSource}-cli", '0', $phpSource);
                    $extension = ExtensionFactory::createForPHPVersion($class, $phpVersion, $osWithPackager);
                    // Like PHPSystemPackage::findExtensionPackage() and Resolver::resolveFromSystemPackage()
                    foreach (PHPSystemPackage::getExtensionPackageCandidates($osWithPackager->getSystemPackager(), $phpPackage, $extension) as $packageName) {
                        $package = self::findPackage($index, $packageName);
                        if ($package === null) {
                            continue;
                        }
                        if (!self::dependsOnOtherPHPVersions($index, $package, $phpSource)) {
                            $withPackage[] = $phpVersion;
                        }
                        break;
                    }
                }
                if ($withPackage !== []) {
                    $result[$class::getHandle()][$os] = $withPackage === $phpVersions ? true : $withPackage;
                }
            }
        }

        return $result;
    }

    /**
     * @return non-empty-list<class-string<\IPE\Extension>>
     */
    private static function getClasses()
    {
        return [
            Extensions\cassandra::class,
            Extensions\grpc::class,
            Extensions\pdo_firebird::class,
            Extensions\phalcon::class,
            Extensions\swoole::class,
        ];
    }

    /**
     * The package indexes used by the images with PHP installed by the system packager.
     * For dnf, the URLs are the ones of the repositories, and phpSource is the source package of PHP ('php' if not specified).
     *
     * @return array<non-empty-string, array{packager: 'apk'|'apt'|'dnf', urls: list<non-empty-string>, phpSource?: non-empty-string}>
     */
    private static function getPackageIndexes()
    {
        return [
            'alpine3.24-apk' => [
                'packager' => 'apk',
                'urls' => [
                    'https://dl-cdn.alpinelinux.org/alpine/v3.24/main/x86_64/APKINDEX.tar.gz',
                    'https://dl-cdn.alpinelinux.org/alpine/v3.24/community/x86_64/APKINDEX.tar.gz',
                ],
            ],
            'bookworm-apt' => [
                'packager' => 'apt',
                'urls' => [
                    'http://deb.debian.org/debian/dists/bookworm/main/binary-amd64/Packages.gz',
                ],
            ],
            'trixie-apt' => [
                'packager' => 'apt',
                'urls' => [
                    'http://deb.debian.org/debian/dists/trixie/main/binary-amd64/Packages.gz',
                ],
            ],
            'trixie-sury' => [
                'packager' => 'apt',
                'urls' => [
                    'https://packages.sury.org/php/dists/trixie/main/binary-amd64/Packages.gz',
                    'http://deb.debian.org/debian/dists/trixie/main/binary-amd64/Packages.gz',
                ],
            ],
            'ubuntu22.04-apt' => [
                'packager' => 'apt',
                'urls' => [
                    'http://archive.ubuntu.com/ubuntu/dists/jammy/main/binary-amd64/Packages.gz',
                    'http://archive.ubuntu.com/ubuntu/dists/jammy/universe/binary-amd64/Packages.gz',
                ],
            ],
            'ubuntu24.04-apt' => [
                'packager' => 'apt',
                'urls' => [
                    'http://archive.ubuntu.com/ubuntu/dists/noble/main/binary-amd64/Packages.gz',
                    'http://archive.ubuntu.com/ubuntu/dists/noble/universe/binary-amd64/Packages.gz',
                ],
            ],
            'ubuntu24.04-sury' => [
                'packager' => 'apt',
                'urls' => [
                    'https://packages.sury.org/php/dists/noble/main/binary-amd64/Packages.gz',
                    'http://archive.ubuntu.com/ubuntu/dists/noble/main/binary-amd64/Packages.gz',
                    'http://archive.ubuntu.com/ubuntu/dists/noble/universe/binary-amd64/Packages.gz',
                ],
            ],
            'ubuntu26.04-apt' => [
                'packager' => 'apt',
                'urls' => [
                    'http://archive.ubuntu.com/ubuntu/dists/resolute/main/binary-amd64/Packages.gz',
                    'http://archive.ubuntu.com/ubuntu/dists/resolute/universe/binary-amd64/Packages.gz',
                ],
            ],
            // AppStream and the repositories of Amazon Linux don't provide any of the slow extensions
            'almalinux8-dnf' => [
                'packager' => 'dnf',
                'urls' => [],
            ],
            'almalinux9-dnf' => [
                'packager' => 'dnf',
                'urls' => [],
            ],
            'almalinux10-dnf' => [
                'packager' => 'dnf',
                'urls' => [],
            ],
            'almalinux9-remi' => [
                'packager' => 'dnf',
                'urls' => [
                    'https://rpms.remirepo.net/enterprise/9/safe/x86_64/',
                ],
                'phpSource' => 'php{major}{minor}-php',
            ],
            'almalinux10-remi' => [
                'packager' => 'dnf',
                'urls' => [
                    'https://rpms.remirepo.net/enterprise/10/safe/x86_64/',
                ],
                'phpSource' => 'php{major}{minor}-php',
            ],
            'amazonlinux2023-dnf' => [
                'packager' => 'dnf',
                'urls' => [],
            ],
            'fedora44-dnf' => [
                'packager' => 'dnf',
                'urls' => [
                    'https://dl.fedoraproject.org/pub/fedora/linux/updates/44/Everything/x86_64/',
                    'https://dl.fedoraproject.org/pub/fedora/linux/releases/44/Everything/x86_64/os/',
                ],
            ],
            'fedora45-dnf' => [
                'packager' => 'dnf',
                'urls' => [
                    'https://dl.fedoraproject.org/pub/fedora/linux/updates/45/Everything/x86_64/',
                    'https://dl.fedoraproject.org/pub/fedora/linux/releases/45/Everything/x86_64/os/',
                ],
            ],
        ];
    }

    /**
     * @param non-empty-string $url the URL of a Packages.gz file
     *
     * @throws \IPE\Exception\IO
     * @throws \RuntimeException
     *
     * @return array{packages: array<string, list<string>>, providers: array<string, list<string>>} the PHP packages, and the PHP packages that provide the virtual ones
     */
    private static function loadAPTIndex($url, Filesystem\Downloader $downloader, Filesystem\VolatileDirectory $volatileDirectory)
    {
        $depends = [];
        $providers = [];
        $file = $volatileDirectory->createSubdir() . $volatileDirectory->getFilesystem()->getDirectorySeparator() . 'Packages.gz';
        $downloader->saveAs($url, $file);
        $handle = gzopen($file, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Failed to decompress {$url}");
        }
        try {
            $package = null;
            $match = null;
            while (($line = gzgets($handle)) !== false) {
                if (preg_match('/^Package:\s*(\S+)/', $line, $match)) {
                    $package = strpos($match[1], 'php') === 0 ? $match[1] : null;
                    if ($package !== null) {
                        $depends[$package] = [];
                    }
                } elseif ($package === null) {
                    continue;
                } elseif (preg_match('/^Depends:(.*)/', $line, $match)) {
                    // Like "Depends: php-psr | php7.3-psr, phpapi-20180731, libc6 (>= 2.14)": APT picks the first alternative
                    foreach (explode(',', $match[1]) as $alternatives) {
                        $depends[$package][] = self::getAPTRelationName(explode('|', $alternatives, 2)[0]);
                    }
                } elseif (preg_match('/^Provides:(.*)/', $line, $match)) {
                    // Like "Provides: php8.4-pdo-firebird (= 8.4.11-1), php-pdo-firebird"
                    foreach (explode(',', $match[1]) as $provided) {
                        $providers[self::getAPTRelationName($provided)][] = $package;
                    }
                }
            }
        } finally {
            gzclose($handle);
        }

        return ['packages' => $depends, 'providers' => $providers];
    }

    /**
     * @param string $relation like "libc6 (>= 2.14)" or "php-psr:any"
     *
     * @return string
     */
    private static function getAPTRelationName($relation)
    {
        return explode(':', trim(explode('(', $relation, 2)[0]), 2)[0];
    }

    /**
     * @param non-empty-string $url the URL of an APKINDEX.tar.gz file
     *
     * @throws \IPE\Exception\IO
     * @throws \RuntimeException
     *
     * @return array{packages: array<string, list<string>>, providers: array<string, list<string>>} the PHP packages, and the PHP packages that provide the virtual ones
     */
    private static function loadAPKIndex($url, Filesystem\Downloader $downloader, Filesystem\ArchiveExtractor $archiveExtractor, Filesystem\VolatileDirectory $volatileDirectory)
    {
        $depends = [];
        $providers = [];
        $fs = $volatileDirectory->getFilesystem();
        $archive = $volatileDirectory->createSubdir() . $fs->getDirectorySeparator() . 'APKINDEX.tar.gz';
        $downloader->saveAs($url, $archive);
        $directory = $archiveExtractor->extractTo($archive, $volatileDirectory->createSubdir(), Filesystem\ArchiveFormat::TAR_GZ);
        $package = null;
        $match = null;
        foreach (explode("\n", $fs->readFile($directory . $fs->getDirectorySeparator() . 'APKINDEX')) as $line) {
            if (preg_match('/^P:(\S+)/', $line, $match)) {
                $package = strpos($match[1], 'php') === 0 ? $match[1] : null;
                if ($package !== null) {
                    $depends[$package] = [];
                }
            } elseif ($package === null) {
                continue;
            } elseif (preg_match('/^([Dp]):(.*)/', $line, $match)) {
                // Like "D:php84-common so:libc.musl-x86_64.so.1" and "p:php84-grpc=1.76.0-r0 cmd:foo"
                foreach (explode(' ', trim($match[2])) as $relation) {
                    $name = substr($relation, 0, strcspn($relation, '=<>~'));
                    if ($match[1] === 'D') {
                        $depends[$package][] = $name;
                    } else {
                        $providers[$name][] = $package;
                    }
                }
            }
        }

        return ['packages' => $depends, 'providers' => $providers];
    }

    /**
     * @param non-empty-string $url the URL of an RPM repository (with the trailing slash)
     *
     * @throws \IPE\Exception\IO
     * @throws \RuntimeException
     *
     * @return array{packages: array<string, list<string>>, providers: array<string, list<string>>} the PHP packages, and the PHP packages that provide the virtual ones
     */
    private static function loadRPMIndex($url, Filesystem\Downloader $downloader, Filesystem\VolatileDirectory $volatileDirectory)
    {
        $fs = $volatileDirectory->getFilesystem();
        $directory = $volatileDirectory->createSubdir() . $fs->getDirectorySeparator();
        $downloader->saveAs("{$url}repodata/repomd.xml", "{$directory}repomd.xml");
        $match = null;
        // The name of the primary metadata includes its checksum, like "repodata/<checksum>-primary.xml.zst"
        if (!preg_match('/<data type="primary">.*?<location href="(?<href>[^"]+)"/s', $fs->readFile("{$directory}repomd.xml"), $match)) {
            throw new \RuntimeException("Failed to find the primary metadata in {$url}repodata/repomd.xml");
        }
        $compressed = $directory . basename($match['href']);
        $downloader->saveAs($url . $match['href'], $compressed);
        $handle = self::openRPMMetadata($compressed, $fs);
        $depends = [];
        $providers = [];
        try {
            $package = null;
            $section = '';
            while (($line = fgets($handle)) !== false) {
                if (preg_match('#^\s*<name>([^<]+)</name>#', $line, $match)) {
                    $package = strpos($match[1], 'php') === 0 ? $match[1] : null;
                    if ($package !== null) {
                        $depends[$package] = [];
                    }
                } elseif ($package === null) {
                    continue;
                } elseif (preg_match('#^\s*<rpm:(provides|requires)>#', $line, $match)) {
                    $section = $match[1];
                } elseif (preg_match('#^\s*</rpm:#', $line)) {
                    $section = '';
                } elseif ($section !== '' && preg_match('#^\s*<rpm:entry name="([^"]+)"#', $line, $match)) {
                    // Like "php85-php-pecl-igbinary" and "php85-php(api)"
                    $name = htmlspecialchars_decode($match[1], ENT_QUOTES);
                    if ($section === 'requires') {
                        $depends[$package][] = $name;
                    } else {
                        $providers[$name][] = $package;
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        return ['packages' => $depends, 'providers' => $providers];
    }

    /**
     * @param non-empty-string $file the compressed primary metadata of an RPM repository
     *
     * @throws \IPE\Exception\IO if the compression is not supported in this environment
     * @throws \RuntimeException
     *
     * @return resource
     */
    private static function openRPMMetadata($file, Filesystem $fs)
    {
        switch (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            case 'gz':
                $wrapper = 'compress.zlib';
                break;
            case 'bz2':
                $wrapper = 'compress.bzip2';
                break;
            case 'zst':
                // Fedora compresses the metadata with zstd
                if ($fs->resolveCommand('zstd') === '') {
                    throw new Exception\IO("The zstd command is required to decompress {$file}");
                }
                $decompressed = substr($file, 0, -4);
                list($rc, $output) = $fs->run(Command::create('zstd', ['-d', '-q', '-o', $decompressed, $file]));
                if ($rc !== 0) {
                    throw new \RuntimeException("Failed to decompress {$file}:\n{$output}");
                }
                $file = $decompressed;
                $wrapper = '';
                break;
            default:
                throw new \RuntimeException("Unsupported compression of {$file}");
        }
        if ($wrapper !== '') {
            if (!in_array($wrapper, stream_get_wrappers(), true)) {
                throw new Exception\IO("The {$wrapper} stream wrapper is required to decompress {$file}");
            }
            $file = "{$wrapper}://{$file}";
        }
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open {$file}");
        }

        return $handle;
    }

    /**
     * @param array{packages: array<string, list<string>>, providers: array<string, list<string>>} $index
     * @param string $name the name of a package, or of a virtual package
     *
     * @return string|null
     */
    private static function findPackage(array $index, $name)
    {
        if (isset($index['packages'][$name])) {
            return $name;
        }

        return isset($index['providers'][$name]) ? $index['providers'][$name][0] : null;
    }

    /**
     * Like PHPSystemPackage::getPackagesOfOtherPHPVersions(), but following the dependencies in the package indexes.
     *
     * @param array{packages: array<string, list<string>>, providers: array<string, list<string>>} $index
     * @param string $package
     * @param string $phpSource like "php8.4" (APT), "php84" (APK), "php" or "php85-php" (Remi SCL)
     * @param array<string, true> $visited
     *
     * @return bool
     */
    private static function dependsOnOtherPHPVersions(array $index, $package, $phpSource, array &$visited = [])
    {
        $visited[$package] = true;
        $phpVersionPrefix = PHPSystemPackage::getPHPVersionPrefix($phpSource);
        foreach ($index['packages'][$package] as $dependency) {
            $versionPrefix = PHPSystemPackage::getPHPVersionPrefix($dependency);
            if ($versionPrefix !== '' && $versionPrefix !== $phpVersionPrefix) {
                return true;
            }
            $dependencyPackage = self::findPackage($index, $dependency);
            if ($dependencyPackage !== null && !isset($visited[$dependencyPackage]) && self::dependsOnOtherPHPVersions($index, $dependencyPackage, $phpSource, $visited)) {
                return true;
            }
        }

        return false;
    }
}
