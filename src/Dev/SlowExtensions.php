<?php

namespace IPE\Dev;

use IPE\Exception;
use IPE\Extensions;
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
            Extensions\cassandra::HANDLE => [],
            Extensions\grpc::HANDLE => [
                'alpine3.24-apk' => true,
                'trixie-sury' => true,
                'ubuntu24.04-sury' => true,
            ],
            Extensions\pdo_firebird::HANDLE => [
                'bookworm-apt' => true,
                'trixie-apt' => true,
                'trixie-sury' => true,
                'ubuntu22.04-apt' => true,
                'ubuntu24.04-apt' => true,
                'ubuntu24.04-sury' => true,
                'ubuntu26.04-apt' => true,
            ],
            Extensions\phalcon::HANDLE => [
                'trixie-sury' => true,
                'ubuntu24.04-sury' => true,
            ],
            Extensions\swoole::HANDLE => [
                'alpine3.24-apk' => true,
                'trixie-sury' => ['8.1', '8.2', '8.3', '8.4', '8.5'],
                'ubuntu24.04-sury' => ['8.2', '8.3', '8.4', '8.5'],
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
            $apk = $packageIndexes[$os]['packager'] === 'apk';
            $availablePackages = [];
            foreach ($packageIndexes[$os]['urls'] as $url) {
                if (!isset($loadedIndexes[$url])) {
                    $loadedIndexes[$url] = $apk ? self::loadAPKIndex($url) : self::loadAPTIndex($url);
                }
                $availablePackages += $loadedIndexes[$url];
            }
            $osWithPackager = $apk ? ExtensionFactory::createOSWithSystemPackager(OS\Alpine::class, SystemPackager\APK::class) : ExtensionFactory::createOSWithSystemPackager(OS\Debian::class, SystemPackager\APT::class);
            foreach (self::getClasses() as $class) {
                $phpVersions = array_values(array_intersect($imagePHPVersions[$os], $class::getSupportedPHPVersions()));
                $withPackage = [];
                foreach ($phpVersions as $phpVersion) {
                    $phpSource = $apk ? 'php' . str_replace('.', '', $phpVersion) : "php{$phpVersion}";
                    $phpPackage = new SystemPackager\Package($apk ? $phpSource : "{$phpSource}-cli", '0', $phpSource);
                    $extension = ExtensionFactory::createForPHPVersion($class, $phpVersion, $osWithPackager);
                    foreach (PHPSystemPackage::getExtensionPackageCandidates($osWithPackager->getSystemPackager(), $phpPackage, $extension) as $packageName) {
                        if (isset($availablePackages[$packageName])) {
                            $withPackage[] = $phpVersion;
                            break;
                        }
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
     *
     * @return array<non-empty-string, array{packager: 'apk'|'apt', urls: non-empty-list<non-empty-string>}>
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
        ];
    }

    /**
     * @param non-empty-string $url the URL of a Packages.gz file
     *
     * @throws \IPE\Exception\IO
     * @throws \RuntimeException
     *
     * @return array<string, true> the names of the PHP packages, including the virtual ones
     */
    private static function loadAPTIndex($url)
    {
        $result = [];
        $file = self::download($url);
        try {
            $handle = gzopen($file, 'rb');
            if ($handle === false) {
                throw new \RuntimeException("Failed to decompress {$url}");
            }
            $match = null;
            while (($line = gzgets($handle)) !== false) {
                if (preg_match('/^Package:\s*(php\S*)/', $line, $match)) {
                    $result[$match[1]] = true;
                } elseif (preg_match('/^Provides:(.*)/', $line, $match)) {
                    // Like "Provides: php8.4-pdo-firebird (= 8.4.11-1), php-pdo-firebird"
                    foreach (explode(',', $match[1]) as $provided) {
                        $name = trim(explode('(', $provided, 2)[0]);
                        if (strpos($name, 'php') === 0) {
                            $result[$name] = true;
                        }
                    }
                }
            }
            gzclose($handle);
        } finally {
            unlink($file);
        }

        return $result;
    }

    /**
     * @param non-empty-string $url the URL of an APKINDEX.tar.gz file
     *
     * @throws \IPE\Exception\IO
     * @throws \RuntimeException
     *
     * @return array<string, true> the names of the PHP packages, including the ones they provide
     */
    private static function loadAPKIndex($url)
    {
        $result = [];
        $file = self::download($url);
        try {
            // Not gzdecode(): the signature and the index are two concatenated gzip streams
            $tar = @file_get_contents("compress.zlib://{$file}");
        } finally {
            unlink($file);
        }
        if (!is_string($tar) || $tar === '') {
            throw new \RuntimeException("Failed to decompress {$url}");
        }
        $match = null;
        foreach (explode("\n", self::extractFromTar($tar, 'APKINDEX', $url)) as $line) {
            if (preg_match('/^P:(php\S*)/', $line, $match)) {
                $result[$match[1]] = true;
            } elseif (preg_match('/^p:(.*)/', $line, $match)) {
                // Like "p:php84-grpc=1.76.0-r0 cmd:foo"
                foreach (explode(' ', trim($match[1])) as $provided) {
                    $name = substr($provided, 0, strcspn($provided, '=<>~'));
                    if (strpos($name, 'php') === 0) {
                        $result[$name] = true;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param string $tar
     * @param non-empty-string $name
     * @param string $url
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    private static function extractFromTar($tar, $name, $url)
    {
        $offset = 0;
        $length = strlen($tar);
        while ($offset + 512 <= $length) {
            $entryName = rtrim(substr($tar, $offset, 100), "\0");
            if ($entryName === '') {
                break;
            }
            $size = octdec(rtrim(substr($tar, $offset + 124, 12), "\0 "));
            if ($entryName === $name) {
                return substr($tar, $offset + 512, (int) $size);
            }
            $offset += 512 + (int) ceil($size / 512) * 512;
        }
        throw new \RuntimeException("{$name} not found in {$url}");
    }

    /**
     * @param non-empty-string $url
     *
     * @throws \IPE\Exception\IO
     * @throws \RuntimeException
     *
     * @return string the path to a temporary file
     */
    private static function download($url)
    {
        $file = tempnam(sys_get_temp_dir(), 'ipe');
        if ($file === false) {
            throw new \RuntimeException('Failed to create a temporary file');
        }
        $context = stream_context_create(['http' => ['timeout' => 60, 'user_agent' => 'install-php-extensions tests']]);
        if (!@copy($url, $file, $context)) {
            unlink($file);
            throw new Exception\IO("Failed to download {$url}");
        }

        return $file;
    }
}
