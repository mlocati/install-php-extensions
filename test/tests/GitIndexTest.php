<?php

namespace IPE\Test;

use IPE\Dev\Git;
use IPE\Test\Support\TestCase;

/**
 * Checks the git index, since the filesystem permissions are unreliable (Windows, files copied to Docker containers).
 */
final class GitIndexTest extends TestCase
{
    /**
     * @var array<string, string>|null the keys are the paths, the values are the modes
     */
    private static $modes;

    /**
     * @return array<string, string>
     */
    private static function getModes()
    {
        if (self::$modes === null) {
            $result = Git::run(['ls-files', '--stage']);
            if ($result === null) {
                self::markTestSkipped('git is not available');
            }
            list($rc, $output) = $result;
            if ($rc !== 0) {
                self::markTestSkipped('This is not a git repository');
            }
            self::$modes = [];
            $output = rtrim(str_replace("\r\n", "\n", $output), "\n");
            foreach ($output === '' ? [] : explode("\n", $output) as $line) {
                // <mode> <object> <stage>\t<path>
                if (!preg_match('/^(\d+) \S+ \d+\t(.+)$/D', $line, $matches)) {
                    self::fail("Unrecognized output of git ls-files: {$line}");
                }
                self::$modes[$matches[2]] = $matches[1];
            }
        }

        return self::$modes;
    }

    /**
     * @return void
     */
    public function testOnlyRegularAndExecutableFiles()
    {
        $others = [];
        foreach (self::getModes() as $path => $mode) {
            if ($mode !== '100644' && $mode !== '100755') {
                $others[$path] = $mode;
            }
        }

        self::assertSame([], $others, 'Symbolic links and submodules are not allowed');
    }

    /**
     * @return void
     */
    public function testExecutableFiles()
    {
        $executables = array_keys(array_filter(self::getModes(), static function ($mode) {
            return $mode === '100755';
        }));
        sort($executables, SORT_STRING);

        self::assertSame(
            [
                'bin/build-phar',
                'bin/install-php-extensions',
                'test/scripts/apt/test-archived',
            ],
            $executables
        );
    }
}
