<?php

namespace IPE\Service;

use IPE\Exception;
use IPE\Filesystem\Command;
use IPE\Filesystem\Downloader;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Traits;

final class ComposerInstaller
{
    use Traits\WarningCatcher;

    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @private
     * @var string
     */
    const INSTALLER_URL = 'https://getcomposer.org/installer';

    /**
     * @private
     * @var string
     */
    const INSTALLERHASH_URL = 'https://composer.github.io/installer.sig';

    /**
     * @private
     * @var string
     */
    const SIGNATURE_HASH = 'sha384';

    /**
     * @var \IPE\Filesystem\Downloader
     */
    private $downloader;

    /**
     * @var \IPE\OS
     */
    private $os;

    /**
     * @var \IPE\PHPInfo
     */
    private $php;

    /**
     * @var \IPE\Service\PHPScript
     */
    private $phpScript;

    public function __construct(Downloader $downloader, OS $os, PHPInfo $php, PHPScript $phpScript, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->downloader = $downloader;
        $this->os = $os;
        $this->php = $php;
        $this->phpScript = $phpScript;
    }

    /**
     * @param string $installPath
     * @param string $version
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function install($installPath = '', $version = '')
    {
        $this->logger->info('# Installing Composer');
        $fs = $this->os->getFilesystem();
        $already = $fs->resolveCommand('composer');
        if ($already !== '') {
            $this->logger->notice('Composer is already installed to ' . $already);
            $fs->run(Command::create($already, ['--version'])->withOutput(Command::OUTPUT_SHOW));

            return;
        }

        list($dir, $file) = $this->resolveInstallPath($installPath);
        $vol = $fs->createVolatileDirectory();
        $installer = $vol->getPath() . '/installer';
        $this->downloader->saveAs(self::INSTALLER_URL, $installer);
        $versionArgument = $this->resolveVersionArgument($version, $installer);
        $this->checkInstallerHash($installer);
        list($rc) = $fs->run($this->phpScript->createCommand($installer, array_merge([
            "--install-dir={$dir}",
            "--filename={$file}",
        ], $versionArgument))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\IO('Composer Installer failed');
        }
        if ($this->os instanceof OS\Windows) {
            $this->createWindowsBat($dir, $file);
        }
    }

    /**
     * @param string $installPath
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return array{non-empty-string, non-empty-string} the directory and the file name
     */
    private function resolveInstallPath($installPath)
    {
        $fs = $this->os->getFilesystem();
        if ($installPath === '') {
            if ($this->os instanceof OS\Posix) {
                $dir = $fs->getRealPath('/usr/local/bin');
                $file = 'composer';
            } elseif ($this->os instanceof OS\Windows) {
                throw new Exception\InvalidArgument('The Composer install path must be specified on Windows');
            } else {
                throw new Exception\InvalidArgument('The Composer install path must be specified on Operating System of class' . get_class($this->os));
            }
        } elseif ($fs->isDirectory($installPath)) {
            $dir = $fs->getRealPath($installPath);
            $file = $this->os instanceof OS\Posix ? 'composer' : 'composer.phar';
        } elseif ($fs->isFile($installPath)) {
            $installPath = str_replace($fs->getDirectorySeparator(), '/', $fs->getRealPath($installPath));
            $dir = $fs->getRealPath(dirname($installPath));
            $file = basename($installPath);
        } else {
            $installPath = str_replace($fs->getDirectorySeparator(), '/', $installPath);
            $dir = dirname($installPath);
            if (!$fs->isDirectory($dir)) {
                throw new Exception\IO("Unable to find the directory '{$dir}' where Composer should be installed");
            }
            $dir = $fs->getRealPath($dir);
            if (!$fs->isWritable($dir)) {
                throw new Exception\IO("The directory '{$dir}' where Composer should be installed is not writable");
            }
            $file = basename($installPath);
        }
        if ($file === '') {
            throw new Exception\InvalidArgument("Invalid Composer install path: '{$installPath}'");
        }

        return [$dir, $file];
    }

    /**
     * @param string $version
     * @param non-empty-string $installer
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return list<non-empty-string>
     */
    private function resolveVersionArgument($version, $installer)
    {
        if ($version === '' || $version === 'stable') {
            return [];
        }
        if (preg_match('/^\d+\.\d+\.\d+.*/', $version)) {
            return ['--version=' . $version];
        }
        if (preg_match('/^\d+(\.\d+)?$/D', $version)) {
            list($rc, $output) = $this->os->getFilesystem()->run(Command::create($this->php->getPHPBin(), ['-n', $installer, '--help']));
            if ($rc !== 0) {
                throw new Exception\IO("Failed to check the Composer installer options:\n{$output}");
            }
            $channels = [];
            $match = null;
            foreach (Regex::split('/[\r\n]+/', $output, -1, true) as $line) {
                $line = trim($line);
                if (preg_match('/^--(?<channel>\d+(\.\d+)?)\s/', $line, $match)) {
                    $channels[] = $match['channel'];
                }
            }
            if (in_array($version, $channels, true)) {
                return ['--' . $version];
            }
            throw new Exception\InvalidArgument("The Composer channel specification ({$version}) is not valid.\nAvailable channels are:\n- " . implode("\n- ", $channels));
        }
        throw new Exception\InvalidArgument("The Composer version specification ({$version}) is not valid");
    }

    /**
     * @param non-empty-string $installer
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function checkInstallerHash($installer)
    {
        if (!function_exists('hash_algos') || !in_array(self::SIGNATURE_HASH, hash_algos(), true)) {
            $this->logger->warning("Composer installed won't be checked since the " . self::SIGNATURE_HASH . ' hashing algorithm is not available');

            return;
        }
        list($actual, $errorDetails) = self::wrap(static function () use ($installer) {
            return hash_file('sha384', $installer);
        }, 'hash_file');
        if ($actual === false) {
            throw new Exception\IO(self::buildErrorMessage('Failed to determing the hash of the Composer Installer', $errorDetails));
        }
        $expected = $this->downloader->getContent(self::INSTALLERHASH_URL);
        if ($actual !== $expected) {
            throw new Exception\IO("Failed to validate Composer Installer.\nExpected hash: {$expected}\nActual hash: {$actual}");
        }
    }

    /**
     * @param non-empty-string $dir
     * @param non-empty-string $pharFile
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function createWindowsBat($dir, $pharFile)
    {
        $fs = $this->os->getFilesystem();
        if (preg_match('/.\.phar$/Di', $pharFile)) {
            $batFile = $dir . $fs->getDirectorySeparator() . substr($pharFile, 0, -5) . '.bat';
            $pharNameInBat = '"%~dpn0.phar"';
        } else {
            $batFile = $dir . $fs->getDirectorySeparator() . "{$pharFile}.bat";
            $pharNameInBat = '"%~dpn0"';
        }
        $content = '@' . $fs->escapeShellArg($this->php->getPHPBin()) . " {$pharNameInBat} %*";

        $fs->writeFile($batFile, $content);
        $this->logger->info("We also created a Windows batch file to make it easier to run Composer:\n{$batFile}");
    }
}
