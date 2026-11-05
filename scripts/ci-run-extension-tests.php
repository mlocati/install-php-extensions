<?php

declare(strict_types=1);

/*
 * Run the extension tests planned by scripts/ci-plan-extension-tests.php in the official Docker PHP images of a distribution.
 * Syntax: php scripts/ci-run-extension-tests.php <distro> <path to install-php-extensions>
 * The tests are read from the IPE_EXTENSION_TESTS environment variable.
 */

namespace IPE\Scripts\CI\RunExtensionTests;

use IPE\CLI\Argv;

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

/**
 * @return list<string>
 */
function getImageCandidates(string $distro, string $phpVersion, string $variant): array
{
    if ($distro === 'jessie' && $phpVersion === '5.5') {
        return ["php:5.5-{$variant}"];
    }

    // The images of PHP versions not released yet have a "-rc" suffix
    return ["php:{$phpVersion}-{$variant}-{$distro}", "php:{$phpVersion}-rc-{$variant}-{$distro}"];
}

/**
 * @param list<string> $candidates
 *
 * @throws \RuntimeException if pulling an image failed for other reasons
 *
 * @return string empty string if none of the images exist
 */
function pullImage(array $candidates): string
{
    foreach ($candidates as $image) {
        [$rc, $output] = run(['docker', 'pull', '--quiet', $image]);
        if ($rc === 0) {
            return $image;
        }
        if (!preg_match('/manifest unknown|not found/i', $output)) {
            throw new \RuntimeException("Failed to pull the Docker image {$image}:\n{$output}");
        }
    }

    return '';
}

function printGroup(string $title, string $contents): void
{
    echo "::group::{$title}\n", rtrim($contents), "\n::endgroup::\n";
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
 *
 * @throws \RuntimeException
 * @throws \JsonException
 */
function runTest(string $image, string $phar, array $extensions): string
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
                '--save-report-to=/ipe/output/report.json',
            ],
            $extensions,
        ));
        if ($rc !== 0) {
            printGroup('Installation failed', $output);

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
            [$rc, $output] = run(['docker', 'exec', '--env', 'LD_BIND_NOW=1', CONTAINER_NAME, 'php', "/ipe/test/{$handle}.php"]);
            echo '   ', rtrim($output), "\n";
            if ($rc !== 0) {
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
 * @return list<array{extensions: non-empty-list<non-empty-string>, php: string, variant: string}>
 */
function getTests(): array
{
    $json = getenv('IPE_EXTENSION_TESTS');
    $data = json_decode(is_string($json) ? $json : '', true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new \RuntimeException('Invalid IPE_EXTENSION_TESTS environment variable');
    }
    $result = [];
    foreach ($data as $test) {
        if (!is_array($test) || !is_string($test['extensions'] ?? null) || !is_string($test['php'] ?? null) || !is_string($test['variant'] ?? null) || !in_array($test['variant'], ['cli', 'zts'], true)) {
            throw new \RuntimeException('Invalid test in IPE_EXTENSION_TESTS: ' . json_encode($test, JSON_THROW_ON_ERROR));
        }
        $extensions = array_values(array_filter(explode(' ', $test['extensions']), static fn (string $extension): bool => $extension !== ''));
        if ($extensions === []) {
            throw new \RuntimeException('Invalid test in IPE_EXTENSION_TESTS: ' . json_encode($test, JSON_THROW_ON_ERROR));
        }
        $result[] = ['extensions' => $extensions, 'php' => $test['php'], 'variant' => $test['variant']];
    }

    return $result;
}

/**
 * @param non-empty-list<string> $argv
 */
function main(array $argv): int
{
    if (count($argv) !== 3 || $argv[1] === '' || !is_file($argv[2])) {
        fwrite(STDERR, "Syntax: {$argv[0]} <distro> <path to install-php-extensions>\n");

        return 1;
    }
    [, $distro, $phar] = $argv;
    $summary = [RESULT_PASSED => [], RESULT_FAILED => [], RESULT_SKIPPED => []];
    try {
        foreach (getTests() as $test) {
            $description = implode(' ', $test['extensions']) . " on PHP {$test['php']}" . ($test['variant'] === 'zts' ? ' (ZTS)' : '');
            echo "### {$description}\n";
            $image = pullImage(getImageCandidates($distro, $test['php'], $test['variant']));
            if ($image === '') {
                echo "   Docker image not available\n";
                continue;
            }
            $start = time();
            $result = runTest($image, $phar, $test['extensions']);
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
