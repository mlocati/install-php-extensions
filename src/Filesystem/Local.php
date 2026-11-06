<?php

namespace IPE\Filesystem;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Service\Regex;
use IPE\Traits;

class Local implements Filesystem
{
    use Traits\WarningCatcher;
    use Traits\Filesystem;

    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var string
     */
    private $temporaryDirectory;

    /**
     * @param string $temporaryDirectory
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     */
    public function __construct(\IPE\Logger $logger, $temporaryDirectory = '')
    {
        $this->logger = $logger;
        if ($temporaryDirectory === '') {
            $this->temporaryDirectory = '';
        } else {
            $temporaryDirectory = $this->getRealPath($temporaryDirectory);
            if (!$this->isDirectory($temporaryDirectory)) {
                throw new Exception\InvalidArgument("'{$temporaryDirectory}' is not a directory");
            }
            if (!$this->isWritable($temporaryDirectory)) {
                throw new Exception\InvalidArgument("The directory '{$temporaryDirectory}' is not writable");
            }
            $this->temporaryDirectory = $temporaryDirectory;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::isLocal()
     */
    public function isLocal()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::getDirectorySeparator()
     */
    public function getDirectorySeparator()
    {
        return DIRECTORY_SEPARATOR;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::getPathSeparator()
     */
    public function getPathSeparator()
    {
        return PATH_SEPARATOR;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::resolveCommand()
     */
    public function resolveCommand($name)
    {
        if ($this->isWindows()) {
            list($restoreWorkDir) = self::wrap(static function () {
                $curDir = getcwd();
                $sysDir = getenv('SystemRoot');

                return $curDir && $sysDir && is_dir($sysDir) && chdir($sysDir) ? $curDir : false;
            });
            try {
                list($rc, $output) = $this->run(Command::create('where.exe', [$name]));
            } finally {
                if ($restoreWorkDir) {
                    self::wrap(static function () use ($restoreWorkDir) {
                        chdir($restoreWorkDir);
                    });
                }
            }
        } else {
            list($rc, $output) = $this->run(Command::create('which', [$name]));
        }

        if ($rc === 0) {
            foreach (Regex::split('/[\r\n]+/', $output, -1, true) as $resolved) {
                $resolved = $this->getNativeSystemPath($resolved);
                if ($this->exists($resolved)) {
                    return $resolved;
                }
            }
        }

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::exists()
     */
    public function exists($path)
    {
        list($result) = self::wrap(static function () use ($path) {
            return file_exists($path);
        });

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::isFile()
     */
    public function isFile($path)
    {
        list($result) = self::wrap(static function () use ($path) {
            return is_file($path);
        });

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::isDirectory()
     */
    public function isDirectory($path)
    {
        list($result) = self::wrap(static function () use ($path) {
            return is_dir($path);
        });

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::isSymlink()
     */
    public function isSymlink($path)
    {
        list($result) = self::wrap(static function () use ($path) {
            return is_link($path);
        });

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::isReadable()
     */
    public function isReadable($path)
    {
        list($result) = self::wrap(static function () use ($path) {
            return is_readable($path);
        });

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::isWritable()
     */
    public function isWritable($path)
    {
        list($result) = self::wrap(static function () use ($path) {
            return is_writable($path);
        });

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::getLastModificationTimestamp()
     */
    public function getLastModificationTimestamp($path)
    {
        list($result, $errorDetails) = self::wrap(static function () use ($path) {
            return filemtime($path);
        });
        if ($result === false) {
            throw new Exception\IO(self::buildErrorMessage("Failed to get the last modification timestamp of '{$path}'", $errorDetails));
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::getFileSize()
     */
    public function getFileSize($path)
    {
        list($result, $errorDetails) = self::wrap(static function () use ($path) {
            // The size may have been changed by an external command (eg strip)
            clearstatcache(true, $path);

            return filesize($path);
        });
        if ($result === false) {
            throw new Exception\IO(self::buildErrorMessage("Failed to get the size of '{$path}'", $errorDetails));
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::readFile()
     */
    public function readFile($path, $maxBytes = null)
    {
        if ($maxBytes === null) {
            $this->logger->debug('Reading file', ['path' => $path]);
            list($result, $errorDetails) = self::wrap(static function () use ($path) {
                return file_get_contents($path);
            }, 'file_get_contents');
        } else {
            $this->logger->debug('Reading first bytes of file', ['path' => $path, 'numBytes' => $maxBytes]);
            list($result, $errorDetails) = self::wrap(static function () use ($path, $maxBytes) {
                $fh = fopen($path, 'r');
                if (!$fh) {
                    return false;
                }
                $result = $maxBytes === 0 ? '' : fread($fh, $maxBytes);
                fclose($fh);

                return $result;
            }, 'fopen');
        }
        if ($result === false) {
            throw new Exception\IO(self::buildErrorMessage("Failed to read file '{$path}'", $errorDetails));
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::writeFile()
     */
    public function writeFile($path, $content)
    {
        $this->logger->debug('Writing file', ['path' => $path, 'content' => $content]);
        list($result, $errorDetails) = self::wrap(static function () use ($path, &$content) {
            return file_put_contents($path, $content);
        }, 'file_put_contents');
        if ($result !== strlen($content)) {
            throw new Exception\IO(self::buildErrorMessage("Failed to write to file '{$path}'", $errorDetails));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::copyFile()
     */
    public function copyFile($from, $to)
    {
        $this->logger->debug('Copying file', ['from' => $from, 'to' => $to]);
        list($result, $errorDetails) = self::wrap(static function () use ($from, $to) {
            return copy($from, $to);
        }, 'copy');
        if ($result === false) {
            throw new Exception\IO(self::buildErrorMessage("Failed to copy file '{$from}' to '{$to}", $errorDetails));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::copyDirectory()
     *
     * WARNING: on Windows with $keepSymlinks === true you'll need the SeCreateSymbolicLinkPrivilege special privilege
     */
    public function copyDirectory($source, $newPath, $keepSymlinks = false)
    {
        if (($source = rtrim($source, '/' . DIRECTORY_SEPARATOR)) === '') {
            throw new Exception\InvalidArgument('Invalid argument $source');
        }
        if (($newPath = rtrim($newPath, '/' . DIRECTORY_SEPARATOR)) === '') {
            throw new Exception\InvalidArgument('Invalid argument $newPath');
        }
        $source = str_replace('/', DIRECTORY_SEPARATOR, $source);
        $newPath = str_replace('/', DIRECTORY_SEPARATOR, $newPath);
        $this->logger->debug('Copying directory', ['from' => $source, 'to' => $newPath]);
        if ($this->exists($newPath)) {
            throw new Exception\IO("Destination already exists: {$newPath}");
        }
        if ($this->isWindows()) {
            if (($robocopy = $this->resolveCommand('robocopy')) !== '') {
                $options = [
                    // Copy subdirectories (including empty ones)
                    '/E',
                    // Don't display progress
                    '/NP',
                    // Don't print job header
                    '/NJH',
                    // 0 retries
                    '/R:0',
                    // 1 second between retries
                    '/W:1',
                ];
                if ($keepSymlinks) {
                    $options = array_merge($options, [
                        // Copies junctions without resolving them
                        '/SJ',
                        // Don't follow symbolic links - copy them
                        '/SL',
                    ]);
                }
                list($robocopyReturnCode, $output) = $this->run(Command::create($robocopy, array_merge($options, [$source, $newPath])));
                // Any value equal to or greater than 8 indicates that there was at least one failure during the copy operation.
                // See https://learn.microsoft.com/en-us/windows-server/administration/windows-commands/robocopy#exit-return-codes
                $rc = ($robocopyReturnCode >= 0 && $robocopyReturnCode < 8) ? 0 : $robocopyReturnCode;
            } elseif (($xcopy = $this->resolveCommand('xcopy')) !== '') {
                $options = [
                    // Copy subdirectories
                    '/S',
                    // Copy also empty subdirectories
                    '/E',
                    // $newPath is the name of a directory
                    '/I',
                    // Quiet
                    '/Q',
                    // Suppress prompts
                    '/Y',
                ];
                if ($keepSymlinks) {
                    $options = array_merge($options, [
                        // Don't follow symbolic links - copy them
                        '/B',
                    ]);
                }
                list($rc, $output) = $this->run(Command::create($xcopy, array_merge($options, [$source, $newPath])));
            } else {
                throw new Exception\IO(self::buildErrorMessage("Failed to copy directory '{$source}' as '{$newPath}'", 'Neither robocopy nor xcopy is available'));
            }
        } else {
            $options = [
                // // Copy subdirectories
                '-R',
            ];
            if ($keepSymlinks) {
                $options = array_merge($options, [
                    // Don't follow follow symbolic links in $source - keep them
                    '-P',
                ]);
            } else {
                $options = array_merge($options, [
                    // Follow symbolic links in $source
                    '-L',
                ]);
            }
            list($rc, $output) = $this->run(Command::create('cp', array_merge($options, [$source, $newPath])));
        }
        if ($rc !== 0) {
            throw new Exception\IO(self::buildErrorMessage("Failed to copy directory '{$source}' as '{$newPath}'", $output));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::createSymlink()
     */
    public function createSymlink($target, $link)
    {
        $this->logger->debug('Creating symlink', ['target' => $target, 'link' => $link]);
        list($result, $errorDetails) = self::wrap(static function () use ($target, $link) {
            return symlink($target, $link);
        }, 'symlink');
        if ($result === false) {
            if (!$this->isWindows()
                || (strpos($errorDetails, '(1314)') === false && strpos($errorDetails, 'Permission denied') === false)
                || !$this->createSymlinkWithMKLink($target, $link)
            ) {
                throw new Exception\IO(self::buildErrorMessage("Failed create a symbolic link of '{$target}' as '{$link}", $errorDetails));
            }
        }
    }

    /**
     * @param non-empty-string $target
     * @param non-empty-string $link
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    private function createSymlinkWithMKLink($target, $link)
    {
        $arguments = [];
        if ($this->isDirectory($target)) {
            $arguments[] = '/D';
        }
        $arguments[] = str_replace('/', DIRECTORY_SEPARATOR, $link);
        $arguments[] = str_replace('/', DIRECTORY_SEPARATOR, $target);
        list($rc) = $this->run(Command::create('MKLINK', $arguments));

        return $rc === 0;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::getRealPath()
     */
    public function getRealPath($path)
    {
        list($result, $errorDetails) = self::wrap(static function () use ($path) {
            return realpath($path);
        }, 'realpath');
        if ($result === false) {
            throw new Exception\IO(self::buildErrorMessage("Failed to get the real path of '{$path}'", $errorDetails));
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::listDirectoryContents()
     */
    public function listDirectoryContents($path)
    {
        list($result, $errorDetails) = self::wrap(static function () use ($path) {
            return scandir($path);
        }, 'scandir');
        if ($result === false) {
            if (!$this->isDirectory($path)) {
                throw $this->exists($path) ? new Exception\IO("'{$path}' is not a directory") : new Exception\IO("The directory '{$path}' does not exist");
            }
            if (!$this->isReadable($path)) {
                throw new Exception\IO("The directory '{$path}' is not readable");
            }
            throw new Exception\IO(self::buildErrorMessage("Failed to list the contents of the the directory '{$path}'", $errorDetails));
        }
        $result = array_filter(
            $result,
            static function ($item) {
                return $item !== '' && $item !== '.' && $item !== '..';
            }
        );
        natcasesort($result);

        return array_values($result);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::getTemporaryDirectory()
     */
    public function getTemporaryDirectory()
    {
        if ($this->temporaryDirectory !== '') {
            return $this->temporaryDirectory;
        }
        foreach ($this->listPotentialTempPaths() as $dir) {
            if ($dir === '') {
                continue;
            }
            try {
                $dir = $this->getRealPath($dir);
            } catch (Exception\IO $e) {
                continue;
            }
            if ($this->isDirectory($dir) && $this->isWritable($dir)) {
                return $this->temporaryDirectory = $dir;
            }
        }
        throw new Exception\IO('Unable to detect an unsable temporary directory');
    }

    /**
     * @return iterable<string>
     */
    protected function listPotentialTempPaths()
    {
        list($result) = self::wrap(static function () {
            return sys_get_temp_dir();
        });
        yield $result;
        foreach (['TMPDIR', 'TMP', 'TEMP'] as $varname) {
            $value = isset($_ENV[$varname]) ? $_ENV[$varname] : getenv($varname);
            if (is_string($value)) {
                yield $value;
            }
        }
        if (DIRECTORY_SEPARATOR === '/') {
            yield '/tmp';
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::createTemporaryFile()
     */
    public function createTemporaryFile()
    {
        $temporaryDirectory = $this->getTemporaryDirectory();
        list($result, $errorDetails) = self::wrap(static function () use ($temporaryDirectory) {
            return tempnam($temporaryDirectory, 'ipe');
        }, 'tempnam');
        if ($result === false) {
            throw new Exception\IO(self::buildErrorMessage('Failed to create a temporary file', $errorDetails));
        }
        $this->logger->debug('Created temporary file', ['path' => $result]);

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::createTemporaryDirectory()
     */
    public function createTemporaryDirectory()
    {
        $temporaryDirectory = $this->getTemporaryDirectory();
        list($result, $errorDetails) = self::wrap(static function () use ($temporaryDirectory) {
            $path = tempnam($temporaryDirectory, 'ipe');
            if ($path === false) {
                return false;
            }

            return unlink($path) === false || mkdir($path, 0777, true) === false ? false : $path;
        }, 'tempnam');
        if ($result === false) {
            throw new Exception\IO(self::buildErrorMessage('Failed to create a temporary directory', $errorDetails));
        }
        $this->logger->debug('Created temporary directory', ['path' => $result]);

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::deleteFile()
     */
    public function deleteFile($path)
    {
        $this->logger->debug('Deleting file', ['path' => $path]);
        list($result, $errorDetails) = self::wrap(static function () use ($path) {
            return unlink($path);
        }, 'unlink');
        if ($result === false) {
            throw new Exception\IO(self::buildErrorMessage("Failed to delete file '{$path}'", $errorDetails));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::createDirectory()
     */
    public function createDirectory($path, $permissions = 0777)
    {
        if ($permissions < 0 || $permissions > 0777) {
            throw new Exception\InvalidArgument('Invalid argument $permissions');
        }
        $this->logger->debug('Creating directory', ['path' => $path]);
        list($result, $errorDetails) = self::wrap(static function () use ($path, $permissions) {
            return mkdir($path, $permissions, true);
        }, 'mkdir');
        if ($result === false) {
            throw new Exception\IO(self::buildErrorMessage("Failed to create directory '{$path}", $errorDetails));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::deleteDirectory()
     */
    public function deleteDirectory($path)
    {
        if ($this->isWindows()) {
            $command = 'rmdir';
            $arguments = ['/S', '/Q', str_replace('/', DIRECTORY_SEPARATOR, $path)];
            // %ERRORLEVEL% will be 0 even if rmdir fails (!)
            $checkExisting = true;
        } else {
            $command = 'rm';
            $arguments = ['-r', '--', $path];
            $checkExisting = false;
        }
        $this->logger->debug('Deleting directory', ['path' => $path]);
        list($rc, $output) = $this->run(Command::create($command, $arguments));
        if ($rc !== 0) {
            throw new Exception\IO(self::buildErrorMessage("Failed to delete directory '{$path}'", trim($output)));
        }
        if ($checkExisting && $this->exists($path)) {
            throw new Exception\IO(self::buildErrorMessage("Failed to delete directory '{$path}'", trim($output)));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::rename()
     */
    public function rename($from, $to)
    {
        if ($this->isDirectory($to)) {
            throw new Exception\IO("You can't rename '{$from}' as '{$to}' since the latter is a directory");
        }
        $this->logger->debug('Renaming', ['from' => $from, 'to' => $to]);
        list($result, $errorDetails) = self::wrap(
            static function () use ($from, $to) {
                return rename($from, $to);
            },
            'rename'
        );
        if ($result === false) {
            throw new Exception\IO(self::buildErrorMessage("Failed to rename '{$from}' to '{$to}'", $errorDetails));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::escapeShellArg()
     */
    public function escapeShellArg($argument)
    {
        $safe = '\w:/.=\-';
        if ($this->isWindows()) {
            $safe .= ',\\\\';
        }

        return preg_match('#^[' . $safe . ']+$#D', $argument) ? $argument : escapeshellarg($argument);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::run()
     */
    public function run(Command $command)
    {
        $workingDirectory = $command->getWorkingDirectory();
        // On POSIX systems proc_open silently ignores non-existing directories
        if ($workingDirectory !== '' && !$this->isDirectory($workingDirectory)) {
            throw new Exception\IO("The working directory '{$workingDirectory}' does not exist");
        }
        $commandLine = $this->prepareFullCommandLine($command);
        $this->logger->debug('Running shell command', ['command' => $commandLine, 'workingDirectory' => $workingDirectory, 'environment' => $command->getEnvironment(), 'output' => $command->getOutput()]);
        if ($this->isWindows() && PHP_VERSION_ID < 80000) {
            // Before PHP 8, cmd.exe would strip the first and last quote of the command line
            $commandLine = "\"{$commandLine}\"";
        }
        $output = $command->getOutput();
        if ($output === Command::OUTPUT_SHOW) {
            return [$this->startAndWait($commandLine, [], $command), ''];
        }
        $nullDevice = ['file', $this->isWindows() ? 'NUL' : '/dev/null', 'w'];
        if ($output === Command::OUTPUT_DISCARD) {
            return [$this->startAndWait($commandLine, [1 => $nullDevice, 2 => $nullDevice], $command), ''];
        }
        // Files instead of pipes: a command can't block by filling a pipe buffer
        $outputFile = $this->createTemporaryFile();
        try {
            if ($output === Command::OUTPUT_CAPTURE_STDOUT) {
                $rc = $this->startAndWait($commandLine, [1 => ['file', $outputFile, 'w'], 2 => $nullDevice], $command);
            } elseif ($output === Command::OUTPUT_CAPTURE_STDERR) {
                $rc = $this->startAndWait($commandLine, [1 => $nullDevice, 2 => ['file', $outputFile, 'w']], $command);
            } else {
                list($handle, $errorDetails) = self::wrap(static function () use ($outputFile) {
                    return fopen($outputFile, 'wb');
                }, 'fopen');
                if ($handle === false) {
                    throw new Exception\IO(self::buildErrorMessage("Failed to open the file '{$outputFile}'", $errorDetails));
                }
                try {
                    // Two "file" descriptors on the same path would overwrite each other on Windows
                    $rc = $this->startAndWait($commandLine, [1 => $handle, 2 => $handle], $command);
                } finally {
                    fclose($handle);
                }
            }

            return [$rc, self::normalizeCapturedOutput($this->readFile($outputFile))];
        } finally {
            $this->deleteFile($outputFile);
        }
    }

    /**
     * @param non-empty-string $commandLine
     * @param array<int, list<string>|resource> $descriptors the descriptors that are not specified are inherited from the current process
     *
     * @throws \IPE\Exception\IO
     *
     * @return int the exit code
     */
    private function startAndWait($commandLine, array $descriptors, Command $command)
    {
        $workingDirectory = $command->getWorkingDirectory();
        // The $env parameter of proc_open replaces the whole environment, and getenv() can't list it before PHP 7.1
        $previousEnvironment = [];
        foreach ($command->getEnvironment() as $name => $value) {
            $previousEnvironment[$name] = getenv($name);
            putenv("{$name}={$value}");
        }
        try {
            list($process, $errorDetails) = self::wrap(static function () use ($commandLine, $descriptors, $workingDirectory) {
                $pipes = [];

                return proc_open($commandLine, $descriptors, $pipes, $workingDirectory === '' ? null : $workingDirectory);
            }, 'proc_open');
        } finally {
            foreach ($previousEnvironment as $name => $value) {
                putenv($value === false ? $name : "{$name}={$value}");
            }
        }
        if (!is_resource($process)) {
            throw new Exception\IO(self::buildErrorMessage("Failed to run command '{$commandLine}'", $errorDetails));
        }

        return proc_close($process);
    }

    /**
     * Remove the line terminators and the trailing whitespace of the lines, so that callers get the same output on every system.
     *
     * @param string $output
     *
     * @return string
     */
    private static function normalizeCapturedOutput($output)
    {
        return rtrim(implode("\n", array_map('rtrim', explode("\n", $output))), "\n");
    }

    /**
     * @return bool
     */
    protected function isWindows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * 32-bit processes accessing System32 are redirected to SysWOW64: use the 64-bit programs instead (via Sysnative).
     *
     * @param non-empty-string $path
     *
     * @return non-empty-string
     */
    private function getNativeSystemPath($path)
    {
        if (PHP_INT_SIZE !== 4 || !$this->isWindows()) {
            return $path;
        }
        $systemRoot = getenv('SystemRoot');
        if (!is_string($systemRoot) || $systemRoot === '') {
            return $path;
        }
        $systemRoot = rtrim($systemRoot, '\\/');
        $prefix = $systemRoot . '\\System32\\';
        if (strcasecmp(substr($path, 0, strlen($prefix)), $prefix) !== 0) {
            return $path;
        }
        $nativePath = $systemRoot . '\\Sysnative\\' . substr($path, strlen($prefix));

        return $this->isFile($nativePath) ? $nativePath : $path;
    }
}
