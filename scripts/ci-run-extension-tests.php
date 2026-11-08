<?php

declare(strict_types=1);

/*
 * Run the extension tests planned by scripts/ci-plan-extension-tests.php in the Docker images of an operating system.
 * Syntax: php scripts/ci-run-extension-tests.php <operating system> <path to install-php-extensions>
 * The tests are read from the IPE_EXTENSION_TESTS environment variable.
 */

namespace IPE\Scripts\CI\RunExtensionTests;

use IPE\CLI\Argv;
use IPE\Dev\DockerImages;

set_error_handler(
    static function (int $errno, string $errstr, string $errfile = '', int $errline = 0): never {
        throw new \RuntimeException("Error {$errno}: {$errstr}" . ($errfile === '' ? '' : " in {$errfile}" . ($errline === 0 ? '' : " at line {$errline}")));
    },
    -1,
);

const ROOT_DIR = __DIR__ . '/..';

require_once ROOT_DIR . '/src/autoload.php';

const CONTAINER_NAME = 'ipe-extension-test';

const RESULT_PASSED = 'passed';

const RESULT_FAILED = 'failed';

const RESULT_SKIPPED = 'skipped';

/**
 * Not exec(): proc_open() with an array doesn't use the shell (so there's no quoting problem), and it doesn't strip the trailing spaces of the lines.
 *
 * @param non-empty-list<string> $arguments
 *
 * @throws \RuntimeException
 *
 * @return array{int, string} the exit code and the output (stdout and stderr)
 */
function run(array $arguments): array
{
    $outputFile = tmpfile();
    if ($outputFile === false) {
        throw new \RuntimeException('Failed to create a temporary file');
    }
    try {
        // The same handle for stdout and stderr, to keep the order of the messages
        $process = proc_open($arguments, [1 => $outputFile, 2 => $outputFile], $pipes);
        if ($process === false) {
            throw new \RuntimeException("Failed to run {$arguments[0]}");
        }
        $rc = proc_close($process);
        rewind($outputFile);
        $output = stream_get_contents($outputFile);
    } finally {
        fclose($outputFile);
    }

    return [$rc, $output === false ? '' : rtrim($output)];
}

function printGroup(string $title, string $contents): void
{
    echo "::group::{$title}\n", rtrim($contents), "\n::endgroup::\n";
}

/**
 * Shown in red in the log, and as an annotation in the summary of the workflow run.
 */
function printError(string $title, string $message): void
{
    $escape = static fn (string $value, bool $isProperty): string => strtr($value, ['%' => '%25', "\r" => '%0D', "\n" => '%0A'] + ($isProperty ? [':' => '%3A', ',' => '%2C'] : []));

    echo '::error title=', $escape($title, true), '::', $escape($message, false), "\n";
}

/**
 * @throws \RuntimeException
 */
function getRealPath(string $path): string
{
    $result = realpath($path);
    if ($result === false) {
        throw new \RuntimeException("Unable to find {$path}");
    }

    return $result;
}

/**
 * @param non-empty-list<non-empty-string> $extensions
 * @param list<string> $options
 *
 * @throws \RuntimeException
 * @throws \JsonException
 */
function runTest(string $description, string $image, string $phar, array $extensions, array $options): string
{
    $outputDir = sys_get_temp_dir() . '/' . uniqid('ipe-extension-test-');
    if (!mkdir($outputDir)) {
        throw new \RuntimeException("Failed to create the directory {$outputDir}");
    }
    [$rc, $output] = run([
        'docker', 'run', '--detach', '--name', CONTAINER_NAME,
        '--volume', getRealPath($phar) . ':/ipe/install-php-extensions:ro',
        '--volume', getRealPath(ROOT_DIR . '/test/extensions') . ':/ipe/test:ro',
        '--volume', "{$outputDir}:/ipe/output",
        '--env', 'CI=true',
        $image,
        'tail', '-f', '/dev/null',
    ]);
    if ($rc !== 0) {
        rmdir($outputDir);

        throw new \RuntimeException("Failed to start the container:\n{$output}");
    }
    try {
        [$rc, $output] = run(array_merge(
            [
                'docker', 'exec', CONTAINER_NAME,
                'php', '/ipe/install-php-extensions',
                '--apt-debian-fix-archived=yes',
                '--fix-letsencrypt=yes',
                '--unsupported-extensions=skip',
                '--download-retry=3',
                '--save-report-to=/ipe/output/report.json',
            ],
            $options,
            $extensions,
        ));
        if ($rc !== 0) {
            printGroup('Installation output', $output);
            printError('Installation failed', "{$description} ({$image})");

            return RESULT_FAILED;
        }
        $report = json_decode((string) file_get_contents("{$outputDir}/report.json"), true, 512, JSON_THROW_ON_ERROR);
        $skipped = [];
        foreach (is_array($report) && is_array($report['skippedExtensions'] ?? null) ? $report['skippedExtensions'] : [] as $skippedExtension) {
            if (is_array($skippedExtension) && is_string($skippedExtension['name'] ?? null)) {
                $skipped[] = $skippedExtension['name'];
                echo "   {$skippedExtension['name']} has been skipped: ", is_string($skippedExtension['message'] ?? null) ? $skippedExtension['message'] : '', "\n";
            }
        }
        $installed = array_values(array_filter($extensions, static fn (string $extension): bool => !in_array(explode(':', $extension, 2)[0], $skipped, true)));
        if ($installed === []) {
            return RESULT_SKIPPED;
        }
        foreach ($installed as $extension) {
            $handle = explode(':', $extension, 2)[0];
            if (!is_file(ROOT_DIR . "/test/extensions/{$handle}.php")) {
                continue;
            }
            // LD_BIND_NOW: with lazy binding, missing symbols are detected only when they are used
            [$rc, $output] = run(['docker', 'exec', '--env', 'LD_BIND_NOW=1', '--env', 'TEST_IPE_OPTIONS=' . json_encode($options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), CONTAINER_NAME, 'php', "/ipe/test/{$handle}.php"]);
            echo '   ', rtrim($output), "\n";
            if ($rc !== 0) {
                printError('Test failed', "{$handle} in {$description} ({$image})");

                return RESULT_FAILED;
            }
        }

        return RESULT_PASSED;
    } finally {
        // The files created by the container are owned by root
        run(['docker', 'exec', CONTAINER_NAME, 'rm', '-f', '/ipe/output/report.json']);
        run(['docker', 'rm', '--force', CONTAINER_NAME]);
        // Not "@rmdir()": the error handler would throw anyway
        try {
            rmdir($outputDir);
        } catch (\RuntimeException) {
        }
    }
}

/**
 * @throws \JsonException
 * @throws \RuntimeException
 * @return list<array{extensions: non-empty-list<non-empty-string>, options: list<string>, php: string, zts: bool, image: string}> the tests to be run on the operating system
 */
function getTests(string $os): array
{
    $json = getenv('IPE_EXTENSION_TESTS');
    $data = json_decode(is_string($json) ? $json : '', true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new \RuntimeException('Invalid IPE_EXTENSION_TESTS environment variable');
    }
    $result = [];
    foreach ($data as $test) {
        if (!is_array($test) || !is_string($test['extensions'] ?? null) || !is_string($test['php'] ?? null) || !is_bool($test['zts'] ?? null)) {
            throw new \RuntimeException('Invalid test in IPE_EXTENSION_TESTS: ' . json_encode($test, JSON_THROW_ON_ERROR));
        }
        $extensions = array_values(array_filter(explode(' ', $test['extensions']), static fn (string $extension): bool => $extension !== ''));
        $rawOptions = $test['options'] ?? [];
        $options = is_array($rawOptions) ? array_values(array_filter($rawOptions, is_string(...))) : [];
        if ($extensions === [] || $options !== $rawOptions) {
            throw new \RuntimeException('Invalid test in IPE_EXTENSION_TESTS: ' . json_encode($test, JSON_THROW_ON_ERROR));
        }
        foreach (DockerImages::find($test['php'], $test['zts']) as $image) {
            if ($image->getOS() === $os) {
                $result[] = ['extensions' => $extensions, 'options' => $options, 'php' => $test['php'], 'zts' => $test['zts'], 'image' => $image->getName()];
            }
        }
    }

    return $result;
}

/**
 * @param non-empty-list<string> $argv
 */
function main(array $argv): int
{
    if (count($argv) !== 3 || $argv[1] === '' || !is_file($argv[2])) {
        fwrite(STDERR, "Syntax: {$argv[0]} <operating system> <path to install-php-extensions>\n");

        return 1;
    }
    [, $os, $phar] = $argv;
    $summary = [RESULT_PASSED => [], RESULT_FAILED => [], RESULT_SKIPPED => []];
    try {
        foreach (getTests($os) as $test) {
            $description = implode(' ', $test['extensions']) . ($test['options'] === [] ? '' : ' (' . implode(' ', $test['options']) . ')') . " on PHP {$test['php']}" . ($test['zts'] ? ' (ZTS)' : '');
            $image = $test['image'];
            echo "### {$description}\n";
            [$rc, $output] = run(['docker', 'pull', '--quiet', $image]);
            if ($rc !== 0) {
                printGroup('docker pull output', $output);
                printError('Failed to pull the Docker image', "{$description} ({$image})");
                $summary[RESULT_FAILED][] = "{$description} ({$image})";
                continue;
            }
            $start = time();
            $result = runTest($description, $image, $phar, $test['extensions'], $test['options']);
            echo "   {$result} in ", time() - $start, " seconds ({$image})\n";
            $summary[$result][] = "{$description} ({$image})";
        }
    } catch (\Throwable $x) {
        fwrite(STDERR, rtrim((string) $x) . "\n");

        return 1;
    }
    echo "\n### SUMMARY\n";
    foreach ($summary as $result => $descriptions) {
        echo ucfirst($result), ":\n", $descriptions === [] ? "(none)\n" : '- ' . implode("\n- ", $descriptions) . "\n";
    }

    return $summary[RESULT_FAILED] === [] ? 0 : 1;
}

exit(main(Argv::getScriptArgv()));
