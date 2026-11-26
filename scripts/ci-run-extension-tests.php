<?php

declare(strict_types=1);

/*
 * Run the extension tests planned by scripts/ci-plan-extension-tests.php in the test environments of an operating system (Docker images, or PHP formulae of Homebrew installed on this macOS runner).
 * Syntax: php scripts/ci-run-extension-tests.php <operating system> <path to install-php-extensions> [<group of the tests> [<PHP version>]]
 * The tests are read from the IPE_EXTENSION_TESTS environment variable.
 * If the IPE_EXTENSION_TESTS_RESULTS environment variable is set, the results are saved as JSON to that file once all the tests have been run.
 */

namespace IPE\Scripts\CI\RunExtensionTests;

use IPE\CLI\Argv;
use IPE\Dev\DockerImage;
use IPE\Dev\HomebrewFormula;
use IPE\Dev\SlowExtensions;
use IPE\Dev\TestEnvironment;
use IPE\Dev\TestEnvironments;
use IPE\Extension;
use IPE\SystemPackager\Homebrew;

set_error_handler(
    static function (int $errno, string $errstr, string $errfile = '', int $errline = 0): never {
        throw new \RuntimeException(
            "Error {$errno}: {$errstr}"
            . (
                $errfile === '' ? '' : " in {$errfile}"
                . (
                    $errline === 0 ? '' : " at line {$errline}"
                )
            ),
        );
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
 * @param array<string, string> $environment the variables to be added to the environment
 *
 * @throws \RuntimeException
 *
 * @return array{int, string} the exit code and the output (stdout and stderr)
 */
function run(array $arguments, array $environment = []): array
{
    $outputFile = tmpfile();
    if ($outputFile === false) {
        throw new \RuntimeException('Failed to create a temporary file');
    }
    try {
        // The same handle for stdout and stderr, to keep the order of the messages
        $process = proc_open($arguments, [1 => $outputFile, 2 => $outputFile], $pipes, null, $environment === [] ? null : array_merge(getenv(), $environment));
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

function printIndented(string $text): void
{
    echo '   ', str_replace("\n", "\n   ", rtrim($text)), "\n";
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
    $escape = static fn (
        string $value,
        bool $isProperty,
    ): string => strtr(
        $value,
        ['%' => '%25', "\r" => '%0D', "\n" => '%0A'] + ($isProperty ? [':' => '%3A', ',' => '%2C'] : []),
    );

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
 * Pull the Docker image or build it from its base image, or install the Homebrew formula.
 *
 * @throws \RuntimeException
 *
 * @return array{int, string, string} the exit code, the output, and the command
 */
function prepareEnvironment(TestEnvironment $environment): array
{
    if ($environment instanceof HomebrewFormula) {
        foreach ([
            'brew install' => ['brew', 'install', '--formula', $environment->getName()],
            'brew tap ' . Homebrew::EXTENSIONS_TAP => ['brew', 'tap', Homebrew::EXTENSIONS_TAP],
            'brew trust ' . Homebrew::EXTENSIONS_TAP => ['brew', 'trust', Homebrew::EXTENSIONS_TAP],
            'brew tap ' . Homebrew::PHP_TAP => ['brew', 'tap', Homebrew::PHP_TAP],
            'brew trust ' . Homebrew::PHP_TAP => ['brew', 'trust', Homebrew::PHP_TAP],
        ] as $command => $arguments) {
            [$rc, $output] = run($arguments);
            if ($rc !== 0) {
                return [$rc, $output, $command];
            }
        }

        return [0, '', 'brew install'];
    }
    if (!$environment instanceof DockerImage) {
        throw new \RuntimeException('Unsupported test environment: ' . $environment::class);
    }
    if ($environment->getBaseImage() === '') {
        return [...run(['docker', 'pull', '--quiet', $environment->getName()]), 'docker pull'];
    }

    return [...buildImage($environment), 'docker build'];
}

/**
 * @throws \RuntimeException
 *
 * @return array{int, string} the exit code and the output
 */
function buildImage(DockerImage $image): array
{
    $dir = sys_get_temp_dir() . '/' . uniqid('ipe-docker-build-');
    if (!mkdir($dir)) {
        throw new \RuntimeException("Failed to create the directory {$dir}");
    }
    try {
        if (file_put_contents(
            "{$dir}/Dockerfile",
            "FROM {$image->getBaseImage()}\nRUN " . implode(" \\\n    && ", $image->getSetupCommands()) . "\n",
        ) === false) {
            throw new \RuntimeException("Failed to write {$dir}/Dockerfile");
        }

        return run(['docker', 'build', '--progress=plain', '--tag', $image->getName(), $dir]);
    } finally {
        unlink("{$dir}/Dockerfile");
        rmdir($dir);
    }
}

/**
 * @throws \RuntimeException
 *
 * @return array{non-empty-string, list<non-empty-string>} the PHP binary of an installed formula, and the directories where install-php-extensions saves the modules and the ini files
 */
function inspectHomebrewFormula(HomebrewFormula $formula): array
{
    /** @var array<string, array{non-empty-string, list<non-empty-string>}> $cache */
    static $cache = [];
    $name = $formula->getName();
    if (isset($cache[$name])) {
        return $cache[$name];
    }
    [$rc, $output] = run(['brew', '--prefix', $name]);
    if ($rc !== 0) {
        throw new \RuntimeException("Failed to get the prefix of {$name}:\n{$output}");
    }
    $php = trim($output) . '/bin/php';
    // Not -n: the php.ini of Homebrew sets extension_dir
    [$rc, $output] = run([$php, '-r', 'echo ini_get("extension_dir"), "\n", PHP_CONFIG_FILE_SCAN_DIR;']);
    if ($rc !== 0) {
        throw new \RuntimeException("Failed to inspect {$php}:\n{$output}");
    }

    return $cache[$name] = [$php, array_values(array_filter(explode("\n", $output), static fn (string $dir): bool => $dir !== ''))];
}

/**
 * @throws \RuntimeException
 *
 * @return list<string>
 */
function listHomebrewFormulae(): array
{
    [$rc, $output] = run(['brew', 'list', '--formula', '-1']);
    if ($rc !== 0) {
        throw new \RuntimeException("Failed to list the Homebrew formulae:\n{$output}");
    }

    // Skip the warnings
    return array_values(array_filter(explode("\n", $output), static fn (string $line): bool => preg_match('/^[a-z0-9][a-z0-9_.+@\-]*$/D', $line) === 1));
}

/**
 * @param list<non-empty-string> $dirs
 *
 * @return list<string>
 */
function listFiles(array $dirs): array
{
    $result = [];
    foreach ($dirs as $dir) {
        foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $name) {
            if (is_file("{$dir}/{$name}")) {
                $result[] = "{$dir}/{$name}";
            }
        }
    }

    return $result;
}

/**
 * The handle of the extension of an argument like "xdebug", "xdebug:^3.3", "xdebug/xdebug" or "php/imap" (the name itself for unknown extensions).
 *
 * @param list<class-string<Extension>> $extensionClasses
 */
function getHandle(string $argument, array $extensionClasses): string
{
    $name = explode(':', $argument, 2)[0];
    if (!str_contains($name, '/')) {
        return $name;
    }
    $class = array_find(
        $extensionClasses,
        static fn (string $class): bool => array_any(
            Extension\Registry::getComposerPackageNames($class),
            static fn (string $composerName): bool => strcasecmp($composerName, $name) === 0,
        ),
    );

    return $class === null ? $name : $class::getHandle();
}

/**
 * Run a command in the container of a Docker image, or on this host.
 *
 * @param non-empty-list<string> $arguments
 * @param array<string, string> $variables the variables to be added to the environment
 *
 * @throws \RuntimeException
 *
 * @return array{int, string} the exit code and the output
 */
function execute(TestEnvironment $environment, array $arguments, array $variables = []): array
{
    if (!$environment instanceof DockerImage) {
        return run($arguments, $variables);
    }
    $envOptions = [];
    foreach ($variables as $variable => $value) {
        array_push($envOptions, '--env', "{$variable}={$value}");
    }

    return run(array_merge(['docker', 'exec'], $envOptions, [CONTAINER_NAME], $arguments));
}

/**
 * @param non-empty-list<non-empty-string> $extensions
 * @param list<string> $options
 * @param list<class-string<Extension>> $extensionClasses
 *
 * @throws \RuntimeException
 * @throws \JsonException
 */
function runTest(string $description, TestEnvironment $environment, string $phar, array $extensions, array $options, array $extensionClasses): string
{
    $name = $environment->getName();
    $outputDir = sys_get_temp_dir() . '/' . uniqid('ipe-extension-test-');
    if (!mkdir($outputDir)) {
        throw new \RuntimeException("Failed to create the directory {$outputDir}");
    }
    if ($environment instanceof DockerImage) {
        [$rc, $output] = run([
            'docker', 'run', '--detach', '--name', CONTAINER_NAME,
            '--volume', getRealPath($phar) . ':/ipe/install-php-extensions:ro',
            '--volume', getRealPath(ROOT_DIR . '/test/extensions') . ':/ipe/test:ro',
            '--volume', "{$outputDir}:/ipe/output",
            '--env', 'CI=true',
            $name,
            'tail', '-f', '/dev/null',
        ]);
        if ($rc !== 0) {
            rmdir($outputDir);

            throw new \RuntimeException("Failed to start the container:\n{$output}");
        }
        $php = $environment->getPHPBinary();
        $ipe = '/ipe/install-php-extensions';
        $testDir = '/ipe/test';
        $report = '/ipe/output/report.json';
        $ipeOptions = ['--apt-debian-fix-archived=yes', '--fix-letsencrypt=yes'];
        // With lazy binding, missing symbols are detected only when they are used
        $bindNow = ['LD_BIND_NOW' => '1'];
        $hostFiles = [];
        $hostDirs = [];
    } elseif ($environment instanceof HomebrewFormula) {
        [$php, $hostDirs] = inspectHomebrewFormula($environment);
        $ipe = getRealPath($phar);
        $testDir = getRealPath(ROOT_DIR . '/test/extensions');
        $report = "{$outputDir}/report.json";
        $ipeOptions = [];
        $bindNow = ['DYLD_BIND_AT_LAUNCH' => '1'];
        // The next tests use the same PHP: what install-php-extensions adds must be removed
        $hostFiles = listFiles($hostDirs);
        $hostFormulae = listHomebrewFormulae();
    } else {
        rmdir($outputDir);

        throw new \RuntimeException('Unsupported test environment: ' . $environment::class);
    }
    try {
        [$rc, $output] = execute($environment, array_merge(
            [
                $php, $ipe,
                ...$ipeOptions,
                '--unsupported-extensions=skip',
                '--download-retry=3',
                "--save-report-to={$report}",
            ],
            $options,
            $extensions,
        ));
        if ($rc !== 0) {
            printError('Installation failed', "{$description} ({$name})");
            printGroup('Installation output', $output);

            return RESULT_FAILED;
        }
        $reportData = json_decode((string) file_get_contents("{$outputDir}/report.json"), true, 512, JSON_THROW_ON_ERROR);
        $skipped = [];
        foreach (is_array($reportData) && is_array($reportData['skippedExtensions'] ?? null) ? $reportData['skippedExtensions'] : [] as $skippedExtension) {
            if (is_array($skippedExtension) && is_string($skippedExtension['name'] ?? null)) {
                $skipped[] = $skippedExtension['name'];
                printIndented(is_string($skippedExtension['message'] ?? null) ? $skippedExtension['message'] : "{$skippedExtension['name']} has been skipped");
            }
        }
        $installed = array_values(
            array_filter(
                array_map(
                    static fn (string $extension): string => getHandle($extension, $extensionClasses),
                    $extensions,
                ),
                static fn (string $handle): bool => !in_array($handle, $skipped, true),
            ),
        );
        if ($installed === []) {
            return RESULT_SKIPPED;
        }
        $result = RESULT_PASSED;
        foreach ($installed as $handle) {
            if (!is_file(ROOT_DIR . "/test/extensions/{$handle}.php")) {
                continue;
            }
            [$rc, $output] = execute($environment, [$php, "{$testDir}/{$handle}.php"], $bindNow + ['TEST_IPE_OPTIONS' => json_encode($options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'TEST_IPE_REPORT' => $report]);
            if ($rc !== 0) {
                printError('Test failed', "{$handle} in {$description} ({$name})");
                printGroup('Test output', $output);
                $result = RESULT_FAILED;
            } else {
                printIndented($output);
            }
        }

        return $result;
    } finally {
        if ($environment instanceof DockerImage) {
            // The files created by the container are owned by root
            run(['docker', 'exec', CONTAINER_NAME, 'rm', '-f', $report]);
            run(['docker', 'rm', '--force', CONTAINER_NAME]);
        } else {
            foreach (array_diff(listFiles($hostDirs), $hostFiles) as $file) {
                unlink($file);
            }
            if (is_file($report)) {
                unlink($report);
            }
            // Otherwise the next tests would find the formulae of the extensions already installed (without their ini files)
            $newFormulae = array_values(array_diff(listHomebrewFormulae(), $hostFormulae));
            if ($newFormulae !== []) {
                [$rc, $output] = run(['brew', 'uninstall', '--formula', '--ignore-dependencies', ...$newFormulae]);
                if ($rc !== 0) {
                    printIndented("Failed to uninstall the new Homebrew formulae:\n{$output}");
                }
            }
        }
        // Not "@rmdir()": the error handler would throw anyway
        try {
            rmdir($outputDir);
        } catch (\RuntimeException) {
        }
    }
}

/**
 * @param string $phpVersion empty string for all
 * @throws \JsonException
 * @throws \RuntimeException
 *
 * @return list<array{extensions: non-empty-list<non-empty-string>, options: list<string>, php: string, zts: bool, environment: TestEnvironment}> the tests of the group to be run on the operating system
 */
function getTests(string $os, string $group, string $phpVersion): array
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
        $extensions = array_values(
            array_filter(
                explode(' ', $test['extensions']),
                static fn (string $extension): bool => $extension !== '',
            ),
        );
        $rawOptions = $test['options'] ?? [];
        $options = is_array($rawOptions) ? array_values(array_filter($rawOptions, is_string(...))) : [];
        $testGroup = $test['group'] ?? '';
        if ($extensions === [] || $options !== $rawOptions || !is_string($testGroup)) {
            throw new \RuntimeException('Invalid test in IPE_EXTENSION_TESTS: ' . json_encode($test, JSON_THROW_ON_ERROR));
        }
        if ($testGroup !== $group || ($phpVersion !== '' && $test['php'] !== $phpVersion)) {
            continue;
        }
        foreach (TestEnvironments::find($test['php'], $test['zts']) as $environment) {
            if ($environment->getOS() !== $os) {
                continue;
            }
            $result[] = ['extensions' => $extensions, 'options' => $options, 'php' => $test['php'], 'zts' => $test['zts'], 'environment' => $environment];
            // Test also the compilation of the extensions (unless they are compiled anyway)
            if (
                $environment->getInstallationsPerExtension() > 1
                && !array_any(
                    $options,
                    static fn (string $option): bool => str_starts_with($option, '--system-packages'),
                )
                && !array_all(
                    $extensions,
                    static fn (string $extension): bool => SlowExtensions::isWithoutSystemPackage($extension, $os, $test['php']),
                )
            ) {
                $result[] = ['extensions' => $extensions, 'options' => array_merge($options, ['--system-packages=never']), 'php' => $test['php'], 'zts' => $test['zts'], 'environment' => $environment];
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
    if (!in_array(count($argv), [3, 4, 5], true) || $argv[1] === '' || !is_file($argv[2])) {
        fwrite(STDERR, "Syntax: {$argv[0]} <operating system> <path to install-php-extensions> [<group of the tests> [<PHP version>]]\n");

        return 1;
    }
    [, $os, $phar] = $argv;
    $group = $argv[3] ?? '';
    $phpVersion = $argv[4] ?? '';
    $summary = [RESULT_PASSED => [], RESULT_FAILED => [], RESULT_SKIPPED => []];
    $results = [];
    try {
        $extensionClasses = \IPE\createApp()->make(Extension\Registry::class)->getExtensionClasses();
        /** @var array<string, bool> $preparedEnvironments */
        $preparedEnvironments = [];
        foreach (getTests($os, $group, $phpVersion) as $test) {
            $description = implode(' ', $test['extensions'])
                . ($test['options'] === [] ? '' : ' (' . implode(' ', $test['options']) . ')')
                . " on PHP {$test['php']}" . ($test['zts'] ? ' (ZTS)' : '');
            $environment = $test['environment']->getName();
            echo "### {$description}\n";
            if (!isset($preparedEnvironments[$environment])) {
                [$rc, $output, $command] = prepareEnvironment($test['environment']);
                $preparedEnvironments[$environment] = $rc === 0;
                if ($rc !== 0) {
                    printError("{$command} failed", "{$description} ({$environment})");
                    printGroup("{$command} output", $output);
                }
            }
            if ($preparedEnvironments[$environment]) {
                $start = time();
                $result = runTest($description, $test['environment'], $phar, $test['extensions'], $test['options'], $extensionClasses);
                $seconds = time() - $start;
                printIndented('Time: ' . ($seconds < 60 ? "{$seconds} seconds" : sprintf('%.1f minutes', $seconds / 60)));
            } else {
                $result = RESULT_FAILED;
            }
            $summary[$result][] = "{$description} ({$environment})";
            $results[] = ['extensions' => implode(' ', $test['extensions']), 'options' => $test['options'], 'php' => $test['php'], 'zts' => $test['zts'], 'environment' => $environment, 'result' => $result];
        }
        $resultsFile = getenv('IPE_EXTENSION_TESTS_RESULTS');
        if (is_string($resultsFile) && $resultsFile !== '' && file_put_contents($resultsFile, json_encode($results, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) === false) {
            throw new \RuntimeException("Failed to write {$resultsFile}");
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
