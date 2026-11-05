<?php

namespace IPE\Dev;

/**
 * Run git commands in the root directory of the repository.
 *
 * @internal
 */
final class Git
{
    /**
     * @var bool|null
     */
    private static $available;

    /**
     * @param list<string> $arguments
     *
     * @throws \RuntimeException
     *
     * @return array{int, string, string}|null the exit code, the standard output and the standard error (not trimmed); NULL if git is not available
     */
    public static function run(array $arguments)
    {
        if (!self::isAvailable()) {
            return null;
        }
        $arguments = array_merge(['git', '-C', dirname(dirname(__DIR__))], $arguments);
        // Files instead of pipes: a command can't block by filling a pipe buffer
        $stdout = tmpfile();
        $stderr = tmpfile();
        if ($stdout === false || $stderr === false) {
            throw new \RuntimeException('Failed to create a temporary file');
        }
        try {
            // An array doesn't need quoting: before PHP 7.4, on Windows escapeshellarg() replaces "%" with a space
            $command = PHP_VERSION_ID >= 70400 ? $arguments : implode(' ', array_map('escapeshellarg', $arguments));
            // bypass_shell: before PHP 8, cmd.exe would strip the first and last quote of the command line
            $process = proc_open($command, [1 => $stdout, 2 => $stderr], $pipes, null, null, ['bypass_shell' => true]);
            if ($process === false) {
                throw new \RuntimeException('Failed to run git');
            }
            $rc = proc_close($process);

            return [$rc, self::readAll($stdout), self::readAll($stderr)];
        } finally {
            fclose($stdout);
            fclose($stderr);
        }
    }

    /**
     * @return bool
     */
    private static function isAvailable()
    {
        if (self::$available === null) {
            $output = [];
            $rc = -1;
            exec(DIRECTORY_SEPARATOR === '\\' ? 'where.exe git >NUL 2>NUL' : 'command -v git >/dev/null 2>&1', $output, $rc);
            self::$available = $rc === 0;
        }

        return self::$available;
    }

    /**
     * @param resource $handle
     *
     * @return string
     */
    private static function readAll($handle)
    {
        rewind($handle);
        $result = stream_get_contents($handle);

        return $result === false ? '' : $result;
    }
}
