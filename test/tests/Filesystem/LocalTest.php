<?php

namespace IPE\Test\Filesystem;

use IPE\Exception;
use IPE\Filesystem\Command;
use IPE\Test\Support\LocalFilesystemTest\LocalWithTemporaryDirectoryNotFound;
use IPE\Test\Support\TestCase;

final class LocalTest extends TestCase
{
    /**
     * @var \IPE\Filesystem\Local
     */
    private static $fs;

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Test\Support\TestCaseBase::doSetUpBeforeClass()
     */
    public static function doSetUpBeforeClass()
    {
        self::$fs = self::createLocalFilesystem();
    }

    /**
     * @return void
     */
    public function testResolveCommandKeepsSymbolicLinks()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Creating symbolic links on Windows requires special privileges');
        }
        $fs = self::$fs;
        $dir = $fs->getTemporaryDirectory() . '/ipe-resolvecommand-' . uniqid();
        $fs->createDirectory($dir);
        $link = "{$dir}/ipe-test-applet";
        symlink('/bin/sh', $link);
        $path = (string) getenv('PATH');
        putenv("PATH={$dir}:{$path}");
        try {
            self::assertSame($link, $fs->resolveCommand('ipe-test-applet'));
        } finally {
            putenv("PATH={$path}");
            unlink($link);
            rmdir($dir);
        }
    }

    /**
     * @return void
     */
    public function testResolveCommandUsesNativeSystemCommands()
    {
        if (DIRECTORY_SEPARATOR !== '\\' || PHP_INT_SIZE !== 4) {
            self::markTestSkipped('Only 32-bit processes on Windows are redirected from System32 to SysWOW64');
        }
        $nativeCmd = rtrim((string) getenv('SystemRoot'), '\\/') . '\Sysnative\cmd.exe';
        if (!is_file($nativeCmd)) {
            self::markTestSkipped('This is a 32-bit Windows');
        }
        self::assertSame(strtolower($nativeCmd), strtolower(self::$fs->resolveCommand('cmd')));
    }

    /**
     * @return void
     */
    public function testLocalFilesystem()
    {
        $fs = self::$fs;
        self::assertTrue($fs->isLocal());
        self::assertSame(DIRECTORY_SEPARATOR, $fs->getDirectorySeparator());
        self::assertSame('', $fs->resolveCommand('this-is-a-command-that-does-not-exist-just-for-testing-ipe'));
        self::assertTrue($fs->isDirectory($fs->getTemporaryDirectory()));
        self::assertTrue($fs->isWritable($fs->getTemporaryDirectory()));
        self::assertTrue($fs->exists(__FILE__));
        self::assertTrue($fs->exists(__DIR__));
        self::assertFalse($fs->exists(__FILE__ . '_non_existing'));
        self::assertTrue($fs->isFile(__FILE__));
        self::assertFalse($fs->isFile(__DIR__));
        self::assertTrue($fs->isDirectory(__DIR__));
        self::assertFalse($fs->isDirectory(__FILE__));
        self::assertFalse($fs->isSymlink(__DIR__));
        self::assertFalse($fs->isSymlink(__FILE__));
        self::assertTrue($fs->isReadable(__DIR__));
        self::assertTrue($fs->isReadable(__DIR__));
        self::assertFalse($fs->isReadable(__FILE__ . '_non_existing'));
        if (DIRECTORY_SEPARATOR === '/') {
            if (is_dir('/sbin') && !is_writable('/sbin')) {
                self::assertFalse($fs->isWritable('/sbin'));
            }
        } else {
            if (is_dir('C:\\Windows') && !is_writable('C:\\Windows')) {
                self::assertFalse($fs->isWritable('C:\\Windows'));
            }
        }
        self::assertFalse($fs->isWritable(__FILE__ . '_non_existing'));
        $content = $fs->readFile(__FILE__);
        self::assertStringContainsString(__FUNCTION__, $content);
        self::assertSame('<?php', $fs->readFile(__FILE__, strlen('<?php')));
        self::assertSame(__FILE__, $fs->getRealPath(__FILE__));
        self::assertSame(__DIR__, $fs->getRealPath(__DIR__));
        $tmp = $fs->createVolatileDirectory();
        $tmpPath = $tmp->getPath();
        self::assertTrue($fs->isDirectory($tmpPath));
        self::assertTrue($fs->isWritable($tmpPath));
        self::assertSame([], $fs->listDirectoryContents($tmpPath));
        $preTimestamp = time();
        $fs->writeFile($tmpPath . '/file', 'FooBar');
        $postTimestamp = time();
        $timestamp = $fs->getLastModificationTimestamp($tmpPath . '/file');
        self::assertTrue($preTimestamp <= ($timestamp + 10) && ($timestamp - 10) <= $postTimestamp, "{$preTimestamp} <= {$timestamp} <= {$postTimestamp}");
        self::assertSame(['file'], $fs->listDirectoryContents($tmpPath));
        self::assertSame('FooBar', $fs->readFile($tmpPath . '/file'));
        $fs->createSymlink($tmpPath . '/file', $tmpPath . '/fileLink');
        self::assertTrue($fs->exists($tmpPath . '/fileLink'));
        self::assertTrue($fs->isSymlink($tmpPath . '/fileLink'));
        self::assertTrue($fs->isFile($tmpPath . '/fileLink'));
        self::assertFalse($fs->isDirectory($tmpPath . '/fileLink'));
        self::assertSame('FooBar', $fs->readFile($tmpPath . '/fileLink'));
        $fs->copyFile($tmpPath . '/fileLink', $tmpPath . '/file2');
        self::assertTrue($fs->isFile($tmpPath . '/file2'));
        self::assertFalse($fs->isSymlink($tmpPath . '/file2'));
        self::assertSame('FooBar', $fs->readFile($tmpPath . '/file2'));
        $fs->writeFile($tmpPath . '/file', 'FooBar2');
        self::assertSame('FooBar2', $fs->readFile($tmpPath . '/fileLink'));
        $fs->createSymlink($tmpPath, $tmpPath . '/dirLink');
        self::assertTrue($fs->exists($tmpPath . '/dirLink'));
        self::assertTrue($fs->isSymlink($tmpPath . '/dirLink'));
        self::assertFalse($fs->isFile($tmpPath . '/dirLink'));
        self::assertTrue($fs->isDirectory($tmpPath . '/dirLink'));
        self::assertTrue($fs->isSymlink($tmpPath . '/dirLink/fileLink'));
        self::assertSame(['dirLink', 'file', 'file2', 'fileLink'], $fs->listDirectoryContents($tmpPath));
        self::assertSame(['dirLink', 'file', 'file2', 'fileLink'], $fs->listDirectoryContents($tmpPath . '/dirLink'));
        $fs->writeFile($tmpPath . '/file', 'FooBar3');
        self::assertSame('FooBar3', $fs->readFile($tmpPath . '/fileLink'));
        self::assertSame('FooBar3', $fs->readFile($tmpPath . '/dirLink/fileLink'));
        $fs->deleteFile($tmpPath . '/file');
        self::assertFalse($fs->exists($tmpPath . '/file'));
        $copySource = $tmpPath . '/copySource';
        $fs->createDirectory($copySource);
        $fs->createDirectory($copySource . '/subdir');
        $fs->createDirectory($copySource . '/subdir/emptySubdir');
        $fs->writeFile($copySource . '/subfile', 'FooBar1');
        $fs->createSymlink($copySource . '/subfile', $copySource . '/subfileLink');
        $fs->writeFile($copySource . '/subdir/subsubfile', 'FooBar2');
        $fs->createSymlink($copySource . '/subdir', $copySource . '/subdirLink');
        self::assertTrue($fs->isSymlink($copySource . '/subdirLink'));
        self::assertTrue($fs->isDirectory($copySource . '/subdirLink'));
        $keepSymlinksCases = [false];
        if (DIRECTORY_SEPARATOR === '\\') {
            list($rc, $output) = $fs->run(Command::create('whoami.exe', ['/PRIV']));
            if ($rc === 0 && preg_match('#^SeCreateSymbolicLinkPrivilege\s#m', $output)) {
                $keepSymlinksCases[] = true;
            }
        } else {
            $keepSymlinksCases[] = true;
        }
        foreach ($keepSymlinksCases as $index => $keepSymlinks) {
            $copyDest = "{$tmpPath}/copyDest{$index}";
            $message = $keepSymlinks ? 'Copy directory keeping symlinks' : 'Copy directory resolving symlinks';
            $fs->copyDirectory($copySource, $copyDest, $keepSymlinks);
            self::assertSame('FooBar1', $fs->readFile($copyDest . '/subfile'), $message);
            self::assertSame('FooBar1', $fs->readFile($copyDest . '/subfileLink'), $message);
            self::assertSame('FooBar2', $fs->readFile($copyDest . '/subdir/subsubfile'), $message);
            self::assertSame($keepSymlinks, $fs->isSymlink($copyDest . '/subfileLink'), $message);
            self::assertSame($keepSymlinks, $fs->isSymlink($copyDest . '/subdirLink'));
            self::assertTrue($fs->isDirectory($copyDest . '/subdirLink'), $message);
            self::assertTrue($fs->isDirectory($copyDest . '/subdir/emptySubdir'), $message);
        }
        unset($tmp);
        gc_collect_cycles();
        self::assertFalse($fs->isDirectory($tmpPath));
        $tmpFile = $fs->createTemporaryFile();
        try {
            self::assertTrue($fs->isFile($tmpFile));
            self::assertSame(0, strpos($tmpFile, $fs->getTemporaryDirectory() . $fs->getDirectorySeparator()));
        } finally {
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * @return list<array{string, list<mixed>, string}>
     */
    public static function provideInvalidArgumentCases()
    {
        return [
            ['copyDirectory', ['/', '/a'], 'Invalid argument $source'],
            ['copyDirectory', ['/a', '/'], 'Invalid argument $newPath'],
            ['createDirectory', ['/foo', -1], 'Invalid argument $permissions'],
            ['createDirectory', ['/foo', 0777 + 1], 'Invalid argument $permissions'],
        ];
    }

    /**
     * @dataProvider provideInvalidArgumentCases
     *
     * @param string $methodName
     * @param list<mixed> $arguments
     * @param string $expectedExceptionMessagePart
     *
     * @return void
     */
    public function testInvalidArgument($methodName, array $arguments, $expectedExceptionMessagePart)
    {
        $fs = self::$fs;
        try {
            $callback = [$fs, $methodName];
            self::assertIsCallable($callback);
            call_user_func_array($callback, $arguments);
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertStringContainsString($expectedExceptionMessagePart, $x->getMessage());
        }
    }

    /**
     * @return array{string, array{mixed}, string}[]
     */
    public static function provideIOExceptionCases()
    {
        if (DIRECTORY_SEPARATOR === '/') {
            $unwritableFilePath = '/';
        } else {
            $unwritableFilePath = 'C:\\';
        }
        $result = [
            ['readFile', [__FILE__ . '_non_existing'], 'Failed to read file'],
            ['writeFile', [$unwritableFilePath, 'test'], 'Failed to write to file'],
            ['copyFile', [__FILE__ . '_non_existing1', __FILE__ . '_non_existing2'], 'Failed to copy file'],
            ['createSymlink', [__FILE__ . '_non_existing', __FILE__], 'Failed create a symbolic link'],
            ['getRealPath', [__FILE__ . '_non_existing'], 'Failed to get the real path'],
            ['listDirectoryContents', [__FILE__], 'is not a directory'],
            ['listDirectoryContents', [__FILE__ . '_non_existing'], 'does not exist'],
            ['deleteFile', [__FILE__ . '_non_existing'], 'Failed to delete file'],
            ['createDirectory', [__DIR__], 'Failed to create directory'],
            ['deleteDirectory', [__FILE__ . '_non_existing'], 'Failed to delete directory'],
        ];
        if (DIRECTORY_SEPARATOR === '/') {
            $unwritableDirectory = is_dir('/sbin') && !is_writable('/sbin') ? '/sbin' : '';
        } else {
            $unwritableDirectory = is_dir('C:\\Windows') && !is_writable('C:\\Windows') ? 'C:\\Windows' : '';
        }
        if ($unwritableDirectory !== '') {
            $result[] = ['createDirectory', [$unwritableDirectory . '/foo'], 'Failed to create directory'];
        }

        return $result;
    }

    /**
     * @dataProvider provideIOExceptionCases
     *
     * @param string $methodName
     * @param array{mixed} $arguments
     * @param string $expectedExceptionMessagePart
     *
     * @return void
     */
    public function testIOException($methodName, array $arguments, $expectedExceptionMessagePart)
    {
        $fs = self::$fs;
        try {
            $callback = [$fs, $methodName];
            self::assertIsCallable($callback);
            call_user_func_array($callback, $arguments);
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString($expectedExceptionMessagePart, $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testTemporaryDirectoryNotFound()
    {
        $fs = new LocalWithTemporaryDirectoryNotFound();
        try {
            $fs->getTemporaryDirectory();
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('Unable to detect', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testRun()
    {
        $fs = self::$fs;
        if (DIRECTORY_SEPARATOR === '\\') {
            $command = '(echo ContentOfStandardOutput & echo ContentOfStandardError >&2)';
        } else {
            $command = '( echo ContentOfStandardOutput ; echo ContentOfStandardError >&2 )';
        }
        list($rc, $outputAll) = $fs->run(Command::raw($command));
        self::assertSame("ContentOfStandardOutput\nContentOfStandardError", $outputAll);
        self::assertSame(0, $rc);
        list($rc, $outputNone) = $fs->run(Command::raw($command)->withOutput(Command::OUTPUT_DISCARD));
        self::assertSame('', $outputNone);
        self::assertSame(0, $rc);
        list($rc, $outputStdout) = $fs->run(Command::raw($command)->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        self::assertSame('ContentOfStandardOutput', $outputStdout);
        self::assertSame(0, $rc);
        list($rc, $outputStderr) = $fs->run(Command::raw($command)->withOutput(Command::OUTPUT_CAPTURE_STDERR));
        self::assertSame('ContentOfStandardError', $outputStderr);
        self::assertSame(0, $rc);
    }

    /**
     * @return void
     */
    public function testRunExitCode()
    {
        $fs = self::$fs;
        foreach ([Command::OUTPUT_CAPTURE_ALL, Command::OUTPUT_CAPTURE_STDOUT, Command::OUTPUT_CAPTURE_STDERR, Command::OUTPUT_SHOW, Command::OUTPUT_DISCARD] as $output) {
            list($rc, $captured) = $fs->run(Command::raw('exit 3')->withOutput($output));
            self::assertSame(3, $rc, $output);
            self::assertSame('', $captured, $output);
        }
    }

    /**
     * @return void
     */
    public function testRunShowsTheOutputOfTheCommand()
    {
        $fs = self::$fs;
        $script = IPE_TEST_TMPDIR . '/run-show.php';
        $autoload = var_export(IPE_ROOT_DIR . '/src/autoload.php', true);
        $fs->writeFile(
            $script,
            <<<EOT
<?php
require {$autoload};
\$fs = new IPE\\Filesystem\\Local(new IPE\\Logger());
list(\$rc) = \$fs->run(IPE\\Filesystem\\Command::create(PHP_BINARY, ['-n', '-r', 'echo 1 + 1;'])->withOutput(IPE\\Filesystem\\Command::OUTPUT_SHOW));
echo "|rc={\$rc}";
EOT
        );
        try {
            list($rc, $output) = $fs->run(Command::create(PHP_BINARY, ['-n', $script]));
        } finally {
            $fs->deleteFile($script);
        }
        self::assertSame('2|rc=0', $output);
        self::assertSame(0, $rc);
    }

    /**
     * @return void
     */
    public function testRunWorkingDirectory()
    {
        $fs = self::$fs;
        $command = Command::create(PHP_BINARY, ['-n', '-r', 'echo getcwd();'])->withWorkingDirectory(IPE_TEST_TMPDIR);
        list($rc, $output) = $fs->run($command);
        self::assertSame(0, $rc);
        self::assertSame(realpath(IPE_TEST_TMPDIR), realpath($output));
        try {
            $fs->run($command->withWorkingDirectory(IPE_TEST_TMPDIR . '/does-not-exist'));
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('does-not-exist', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testRunEnvironment()
    {
        $fs = self::$fs;
        $name = 'IPE_TEST_RUN_ENVIRONMENT';
        $read = Command::create(PHP_BINARY, ['-n', '-r', "var_export(getenv('{$name}') . '|' . (getenv('PATH') === false ? 'nopath' : 'path'));"]);
        self::assertFalse(getenv($name));
        list(, $output) = $fs->run($read->withEnvironment([$name => 'new value']));
        self::assertSame("'new value|path'", $output);
        self::assertFalse(getenv($name), 'The variable must be removed from the current process');
        putenv("{$name}=previous");
        try {
            list(, $output) = $fs->run($read->withEnvironment([$name => 'new value']));
            self::assertSame("'new value|path'", $output);
            self::assertSame('previous', getenv($name), 'The variable must be restored in the current process');
        } finally {
            putenv($name);
        }
    }

    /**
     * @return void
     */
    public function testRunRawCommandAppliesEverythingToTheWholeCommandLine()
    {
        $fs = self::$fs;
        $name = 'IPE_TEST_RUN_RAW';
        $commandLine = DIRECTORY_SEPARATOR === '\\' ? "echo %{$name}%& echo %{$name}%>&2" : "echo \"\${$name}\"; echo \"\${$name}\" >&2";
        list($rc, $output) = $fs->run(Command::raw($commandLine)->withEnvironment([$name => 'value']));
        self::assertSame(0, $rc);
        self::assertSame("value\nvalue", $output);
    }

    /**
     * @return void
     */
    public function testRunBigOutput()
    {
        $fs = self::$fs;
        // More than any pipe buffer, on both streams
        $code = "for (\$i = 0; \$i < 2000; \$i++) { echo str_repeat('o', 99), PHP_EOL; fwrite(STDERR, str_repeat('e', 99) . PHP_EOL); }";
        list($rc, $output) = $fs->run(Command::create(PHP_BINARY, ['-n', '-r', $code]));
        self::assertSame(0, $rc);
        self::assertCount(4000, explode("\n", $output));
    }

    /**
     * @return void
     */
    public function testResolvingCommand()
    {
        $fs = self::$fs;
        $tmp = $fs->createVolatileDirectory();
        $restoreWorkingDirectory = getcwd();
        self::assertTrue(chdir($tmp->getPath()));
        try {
            self::assertIsString($restoreWorkingDirectory);
            if (DIRECTORY_SEPARATOR === '\\') {
                $fs->writeFile($tmp->getPath() . '/where.exe', '');
                $fs->writeFile($tmp->getPath() . '/cmd.bat', '');
                $command = $fs->resolveCommand('cmd');
                self::assertMatchesRegularExpression('#\\\\cmd.exe$#iD', $command);
            } else {
                $fs->writeFile($tmp->getPath() . '/which', '');
                self::assertTrue(chmod($tmp->getPath() . '/which', 0777));
                $fs->writeFile($tmp->getPath() . '/sh', '');
                self::assertTrue(chmod($tmp->getPath() . '/sh', 0777));
                $command = $fs->resolveCommand('sh');
                self::assertMatchesRegularExpression('#bin/[a-z]*sh$#iD', $command);
            }
            self::assertSame($tmp->getPath(), getcwd());
        } finally {
            if ($restoreWorkingDirectory !== false) {
                @chdir($restoreWorkingDirectory);
            }
        }
    }

    /**
     * @return void
     */
    public function testCreateSubdir()
    {
        $fs = self::$fs;
        $tmp = $fs->createVolatileDirectory();
        $prev = null;
        for ($i = 0; $i < 3; $i++) {
            $subdir = $tmp->createSubdir();
            self::assertTrue(is_dir($subdir));
            self::assertMatchesRegularExpression(
                '/^' . preg_quote($tmp->getPath() . $fs->getDirectorySeparator(), '/') . 's\d+$/D',
                $subdir
            );
            self::assertSame(realpath($subdir), $subdir);
            self::assertSame([], $fs->listDirectoryContents($subdir));
            if ($prev !== null) {
                self::assertNotSame($prev, $subdir);
            }
            $prev = $subdir;
        }
    }

    /**
     * @return void
     */
    public function testRename()
    {
        $fs = self::$fs;
        $tmp = $fs->createVolatileDirectory();
        $dir1 = $tmp->getPath() . '/dir1';
        $dir2 = $tmp->getPath() . '/dir2';
        $fs->createDirectory($dir1);
        $fs->writeFile($tmp->getPath() . '/file1', 'foo');
        $fs->rename($tmp->getPath() . '/file1', $dir1 . '/file2');
        self::assertFalse($fs->exists($tmp->getPath() . '/file1'));
        self::assertSame('foo', $fs->readFile($dir1 . '/file2'));
        $fs->rename($dir1, $dir2);
        self::assertFalse($fs->exists($dir1));
        self::assertSame('foo', $fs->readFile($dir2 . '/file2'));
        try {
            $fs->rename($dir1, $dir2);
            self::fail('Expected exception not thrown');
        } catch (Exception $x) {
            self::assertInstanceOf(Exception\IO::class, $x);
        }
        $dir3 = $tmp->getPath() . '/dir3';
        $fs->createDirectory($dir3);
        try {
            $fs->rename($dir2, $dir3);
            self::fail('Expected exception not thrown');
        } catch (Exception $x) {
            self::assertInstanceOf(Exception\IO::class, $x);
        }
        $fileA = $tmp->getPath() . '/fileA';
        $fileB = $tmp->getPath() . '/fileB';
        $fs->writeFile($fileA, 'A');
        $fs->writeFile($fileB, 'B');
        $fs->rename($fileA, $fileB);
        self::assertFalse($fs->exists($fileA));
        self::assertSame('A', $fs->readFile($fileB));
    }
}
