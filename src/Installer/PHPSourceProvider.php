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
        if ($this->archiveExtractor->canHandleFormat(ArchiveFormat::TAR_XZ)) {
            $fileExtension = '.tar.xz';
        } else {
            $fileExtension = '.tar.gz';
        }
        $urls = $this->getPHPSourceURLs($state->getPHP()->getVersion(), $fileExtension);
        if ($urls !== []) {
            $downloadToVolatile = $state->getVolatileDir()->getFilesystem()->createVolatileDirectory();
            foreach ($urls as $index => $url) {
                $downloadToFile = $downloadToVolatile->getPath() . '/php-src' . $index . $fileExtension;
                try {
                    $this->downloader->saveAs($url, $downloadToFile);
                } catch (Exception\IO\NotFound $_) {
                    continue;
                }
                $dir = $state->getVolatileDir()->createSubdir();

                return $this->archiveExtractor->extractTo($downloadToFile, $dir);
            }
        }
        $downloadToVolatile = $state->getVolatileDir()->getFilesystem()->createVolatileDirectory();

        throw new Exception\IO("Unable to determine the location of the source code for PHP {$state->getPHP()->getVersion()}");
    }

    /**
     * @param non-empty-string $phpVersion
     * @param non-empty-string $fileExtension
     *
     * @return list<non-empty-string>
     */
    private function getPHPSourceURLs($phpVersion, $fileExtension)
    {
        $matches = null;
        if (!preg_match('#^(?<base>\d+\.\d+\.\d+)(?<stability>(alpha|beta|RC)\d+)?$#D', $phpVersion, $matches)) {
            return [];
        }
        $base = $matches['base'];
        $stability = isset($matches['stability']) ? $matches['stability'] : '';
        if ($stability === '') {
            return [
                "https://www.php.net/distributions/php-{$base}{$fileExtension}",
            ];
        }
        $result = [];
        if (strpos($base, '8.6.') === 0) {
            $result[] = "https://downloads.php.net/~daniels/php-{$base}{$stability}{$fileExtension}";
            $result[] = "https://downloads.php.net/~mbeccati/php-{$base}{$stability}{$fileExtension}";
            $result[] = "https://downloads.php.net/~svpernova09/php-{$base}{$stability}{$fileExtension}";
        }
        if (strpos($base, '8.5.') === 0) {
            $result[] = "https://downloads.php.net/~daniels/php-{$base}{$stability}{$fileExtension}";
            $result[] = "https://downloads.php.net/~edorian/php-{$base}{$stability}{$fileExtension}";
        }
        if (strpos($base, '8.4.') === 0) {
            $result[] = "https://downloads.php.net/~calvinb/php-{$base}{$stability}{$fileExtension}";
            $result[] = "https://downloads.php.net/~saki/php-{$base}{$stability}{$fileExtension}";
        }
        if (strpos($base, '8.3.') === 0) {
            $result[] = "https://downloads.php.net/~jakub/php-{$base}{$stability}{$fileExtension}";
            $result[] = "https://downloads.php.net/~eric/php-{$base}{$stability}{$fileExtension}";
        }
        if (strpos($base, '8.2.') === 0) {
            $result[] = "https://downloads.php.net/~sergey/php-{$base}{$stability}{$fileExtension}";
            $result[] = "https://downloads.php.net/~pierrick/php-{$base}{$stability}{$fileExtension}";
        }
        if (strpos($base, '8.1.') === 0) {
            $result[] = "https://downloads.php.net/~patrickallaert/php-{$base}{$stability}{$fileExtension}";
            $result[] = "https://downloads.php.net/~ramsey/php-{$base}{$stability}{$fileExtension}";
        }
        if (strpos($base, '8.0.') === 0) {
            $result[] = "https://downloads.php.net/~pollita/php-{$base}{$stability}{$fileExtension}";
            $result[] = "https://downloads.php.net/~derick/php-{$base}{$stability}{$fileExtension}";
        }

        return $result;
    }
}
