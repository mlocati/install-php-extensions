<?php

namespace IPE\Installer;

use IPE\Exception;
use IPE\Filesystem\ArchiveExtractor;
use IPE\Filesystem\ArchiveFormat;
use IPE\Filesystem\Downloader;

class PHPSourceProvider
{
    /**
     * @var \IPE\Filesystem\Downloader
     */
    private $downloader;

    /**
     * @var \IPE\Filesystem\ArchiveExtractor
     */
    private $archiveExtractor;

    public function __construct(Downloader $downloader, ArchiveExtractor $archiveExtractor)
    {
        $this->downloader = $downloader;
        $this->archiveExtractor = $archiveExtractor;
    }

    /**
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     *
     * @return non-empty-string
     */
    public function providePHPSourceDirectory(State $state)
    {
        if ($state->getFilesystem()->getDirectorySeparator() !== '/') {
            throw new Exception\UnsupportedOS('Fetching PHP Source is only supported on POSIX-like systems');
        }

        return $this->tryWithPHPDockerImages($state) ?: $this->fetch($state);
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     *
     * @return string
     */
    private function tryWithPHPDockerImages(State $state)
    {
        $fs = $state->getFilesystem();
        $command = $fs->resolveCommand('docker-php-source');
        if ($command === '') {
            return '';
        }
        foreach ([
            '/usr/src/php.tar.xz',
            '/usr/src/php.tar.bz2',
            '/usr/src/php.tar.gz',
        ] as $archive) {
            if ($fs->exists($archive)) {
                return $this->archiveExtractor->extractTo(
                    $archive,
                    $state->getVolatileDir()->createSubdir()
                );
            }
        }

        return '';
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     *
     * @return non-empty-string
     */
    private function fetch(State $state)
    {
        $phpVersion = $state->getPHP()->getVersion();
        $sources = self::getPHPSourceURLs($phpVersion, $this->archiveExtractor->canHandleFormat(ArchiveFormat::TAR_XZ));
        if ($sources !== []) {
            $downloadToVolatile = $state->getVolatileDir()->getFilesystem()->createVolatileDirectory();
            foreach ($sources as $index => list($url, $format)) {
                $downloadToFile = $downloadToVolatile->getPath() . "/php-src{$index}.{$format}";
                try {
                    $this->downloader->saveAs($url, $downloadToFile);
                } catch (Exception\IO\NotFound $_) {
                    continue;
                }

                return $this->archiveExtractor->extractTo($downloadToFile, $state->getVolatileDir()->createSubdir(), $format);
            }
        }

        throw new Exception\IO("Unable to find the source code of PHP {$phpVersion}");
    }

    /**
     * @param non-empty-string $phpVersion
     * @param bool $allowXZ can we extract tar.xz archives? (they are smaller than the tar.gz ones)
     *
     * @return list<array{non-empty-string, \IPE\Filesystem\ArchiveFormat::TAR_GZ|\IPE\Filesystem\ArchiveFormat::TAR_XZ}> the URLs and the formats of the archives
     */
    private static function getPHPSourceURLs($phpVersion, $allowXZ)
    {
        $matches = null;
        // The distributions add their suffixes (for example 8.1.2-1ubuntu2.14), -dev is used for the snapshots that weren't released
        if (!preg_match('#^(?<base>(?<majorMinor>\d+\.\d+)\.\d+)(?<stability>(?:alpha|beta|RC)\d+)?(?<suffix>.*)$#sD', $phpVersion, $matches) || strpos($matches['suffix'], '-dev') === 0) {
            return [];
        }
        $base = $matches['base'];
        $stability = $matches['stability'];
        $format = $allowXZ ? ArchiveFormat::TAR_XZ : ArchiveFormat::TAR_GZ;
        $result = [];
        if ($stability === '') {
            $result[] = ["https://www.php.net/distributions/php-{$base}.{$format}", $format];
        } else {
            $releaseManagers = [
                '8.6' => ['daniels', 'mbeccati', 'svpernova09'],
                '8.5' => ['daniels', 'edorian'],
                '8.4' => ['calvinb', 'saki'],
                '8.3' => ['jakub', 'eric'],
                '8.2' => ['sergey', 'pierrick'],
                '8.1' => ['patrickallaert', 'ramsey'],
                '8.0' => ['pollita', 'derick'],
            ];
            if (isset($releaseManagers[$matches['majorMinor']])) {
                foreach ($releaseManagers[$matches['majorMinor']] as $releaseManager) {
                    $result[] = ["https://downloads.php.net/~{$releaseManager}/php-{$base}{$stability}.{$format}", $format];
                }
            }
        }
        // Last resort: the archives of the tags lack the files generated for the releases (for example by re2c)
        $result[] = ["https://github.com/php/php-src/archive/refs/tags/php-{$base}{$stability}." . ArchiveFormat::TAR_GZ, ArchiveFormat::TAR_GZ];

        return $result;
    }
}
