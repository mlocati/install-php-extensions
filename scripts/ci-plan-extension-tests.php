<?php

declare(strict_types=1);

/*
 * Plan the extension tests.
 * Syntax:
 * - php scripts/ci-plan-extension-tests.php <base commit> <head commit>
 *   For pull requests (see CONTRIBUTING.md)
 * - php scripts/ci-plan-extension-tests.php --quick [<commit before> <commit after>]
 *   For pushes and manual runs: install one extension in a few Docker images (if the commits are specified, only if relevant files changed)
 * Writes to the file specified by the GITHUB_OUTPUT environment variable (if set):
 * - distros: the JSON array of the operating systems of the Docker images to be used (empty if there's nothing to test)
 * - tests: the JSON array of the tests, each one with the space-separated extensions, the PHP version, whether PHP is thread-safe, and the options of install-php-extensions (if any)
 */

namespace IPE\Scripts\CI\PlanExtensionTests;

use IPE\CLI\Argv;
use IPE\Dev\DockerImage;
use IPE\Dev\DockerImages;
use IPE\Dev\ExtensionFactory;
use IPE\Dev\Git;
use IPE\Extension;

set_error_handler(
    static function (int $errno, string $errstr, string $errfile = '', int $errline = 0): never {
        throw new \RuntimeException("Error {$errno}: {$errstr}" . ($errfile === '' ? '' : " in {$errfile}" . ($errline === 0 ? '' : " at line {$errline}")));
    },
    -1,
);

define('IPE_CLI_ROOT_DIR', dirname(__DIR__));

require_once IPE_CLI_ROOT_DIR . '/src/autoload.php';
require_once IPE_CLI_ROOT_DIR . '/src/app.php';

/**
 * When one of these files changes, at least one extension must be tested.
 */
const PATHS_REQUIRING_TESTS_REGEX = [
    // The contents of the PHAR files
    '{^bin/install-php-extensions$}',
    '{^src/(?!Dev/).+\.php$}',
    '{^composer\.(?:json|lock)$}',
    // How the PHAR files are built
    '{^bin/build-phar$}',
    // How the extensions are tested
    '{^scripts/ci-(?:plan|run)-extension-tests\.php$}',
    '{^\.github/workflows/tests\.yml$}',
    '{^test/extensions/_bootstrap\.php$}',
    '{^src/Dev/DockerImages?\.php$}',
];

/**
 * A bundled extension without system dependencies, so that the quick test is fast.
 */
const QUICK_TEST_EXTENSION = 'calendar';

const QUICK_TEST_PHP_VERSION = '8.5';

/**
 * The extensions to be tested more times, each time with different install-php-extensions options (an empty list means the default options).
 */
const OPTION_VARIANTS = [
    'saxon' => [
        [],
        ['--saxon:edition=EE'],
        ['--saxon:edition=PE'],
        ['--saxon:edition=HE'],
    ],
];

final class RequestedTest
{
    /**
     * @param non-empty-list<non-empty-string> $extensions
     * @param list<non-empty-string>|null $phpVersions NULL for all the PHP versions
     */
    public function __construct(
        public readonly array $extensions,
        public readonly ?array $phpVersions,
        public readonly string $source,
    ) {
    }

    /**
     * Sorted, so that a+b and b+a are the same test.
     */
    public function getKey(): string
    {
        $extensions = $this->extensions;
        sort($extensions, SORT_STRING);

        return implode(' ', $extensions);
    }
}

/**
 * @param list<string> $arguments
 *
 * @throws \RuntimeException
 *
 * @return list<string> the lines of the standard output
 */
function git(array $arguments): array
{
    $result = Git::run($arguments);
    if ($result === null) {
        throw new \RuntimeException('git is not available');
    }
    [$rc, $output, $error] = $result;
    if ($rc !== 0) {
        throw new \RuntimeException('git ' . implode(' ', $arguments) . " failed (exit code: {$rc}):\n" . trim($error));
    }
    $output = rtrim(str_replace("\r\n", "\n", $output), "\n");

    return $output === '' ? [] : explode("\n", $output);
}

/**
 * @throws \RuntimeException
 *
 * @return list<string> the lines of the message (including the subject)
 */
function getCommitMessage(string $hash): array
{
    return git(['log', '-n', '1', '--format=%B', $hash]);
}

/**
 * @return list<non-empty-string>
 */
function splitList(string $list): array
{
    return array_values(array_filter(preg_split('/[\s,;]+/', $list) ?: [], static fn (string $item): bool => $item !== ''));
}

/**
 * Inspect the commits starting from the most recent one, until a "-STOP-" is found.
 *
 * @throws \RuntimeException
 *
 * @return array{list<RequestedTest>, bool} the requested tests, and whether "-STOP-" has been found
 */
function collectTestsFromCommits(string $base, string $head): array
{
    echo "### Commits of the pull request (from the most recent)\n";
    $result = [];
    foreach (git(['rev-list', "{$base}..{$head}"]) as $hash) {
        $shortHash = substr($hash, 0, 7);
        $message = getCommitMessage($hash);
        echo "- {$shortHash} ", $message[0] ?? '', "\n";
        $tests = [];
        $phpVersions = null;
        $stop = false;
        foreach ($message as $line) {
            if (preg_match('/^\s*Test-PHP:(.*)$/i', $line, $matches)) {
                echo '  ', trim($line), "\n";
                $phpVersions = array_merge($phpVersions ?? [], splitList($matches[1]));
                continue;
            }
            if ($stop || !preg_match('/^\s*Test:(.*)$/i', $line, $matches)) {
                continue;
            }
            echo '  ', trim($line), "\n";
            foreach (splitList($matches[1]) as $item) {
                if ($item === '-STOP-') {
                    // Keep reading the message: Test-PHP may follow
                    $stop = true;
                    break;
                }
                $extensions = array_values(array_filter(explode('+', $item), static fn (string $extension): bool => $extension !== ''));
                if ($extensions !== []) {
                    $tests[] = $extensions;
                }
            }
        }
        foreach ($tests as $extensions) {
            $result[] = new RequestedTest($extensions, $phpVersions === null ? null : array_values(array_unique($phpVersions)), "commit {$shortHash}");
        }
        if ($stop) {
            echo "  -STOP- found: the older commits are not inspected, and the extensions whose files have been changed are not tested automatically\n";

            return [$result, true];
        }
    }
    if ($result === []) {
        echo "No extensions requested in the commit messages\n";
    }

    return [$result, false];
}

/**
 * @param list<string> $changedFiles
 * @param array<string, class-string<Extension>> $extensionClasses
 *
 * @return list<RequestedTest>
 */
function collectTestsFromChanges(array $changedFiles, array $extensionClasses): array
{
    echo "### Changed files of the supported extensions\n";
    $handles = [];
    foreach ($changedFiles as $path) {
        if (preg_match('_^(?:src/Extensions|test/extensions)/(?<handle>[^/]+)(?:\.php$|/)_', $path, $matches) && isset($extensionClasses[$matches['handle']])) {
            echo "- {$path} ({$matches['handle']})\n";
            $handles[$matches['handle']] = true;
        }
    }
    if ($handles === []) {
        echo "(none)\n";
    }
    $handles = array_keys($handles);
    sort($handles, SORT_STRING);

    return array_map(static fn (string $handle): RequestedTest => new RequestedTest([$handle], null, 'changed files'), $handles);
}

/**
 * @param list<string> $changedFiles
 *
 * @return list<string>
 */
function getFilesRequiringTests(array $changedFiles): array
{
    return array_values(array_filter($changedFiles, static fn (string $path): bool => array_any(PATHS_REQUIRING_TESTS_REGEX, static fn (string $regex): bool => preg_match($regex, $path) === 1)));
}

/**
 * @param array{string, string}|null $commits the commits before and after a push
 *
 * @throws \RuntimeException
 */
function isQuickTestNeeded(?array $commits): bool
{
    echo "### Quick test\n";
    if ($commits === null) {
        echo "No commits specified: the quick test is needed\n";

        return true;
    }
    [$before, $after] = $commits;
    // The commit before the push is all zeroes for new branches, and it may be missing after a force push
    $result = preg_match('/^0+$/D', $before) === 1 ? null : Git::run(['cat-file', '-e', "{$before}^{commit}"]);
    if ($result === null || $result[0] !== 0) {
        echo "The commit {$before} is not available: the quick test is needed\n";

        return true;
    }
    $filesRequiringTests = getFilesRequiringTests(git(['diff', '--name-only', '--no-renames', $before, $after]));
    if ($filesRequiringTests === []) {
        echo "No changed files affect install-php-extensions or its tests: the quick test is not needed\n";

        return false;
    }
    echo "These files affect install-php-extensions or its tests:\n- " . implode("\n- ", $filesRequiringTests) . "\n";

    return true;
}

/**
 * The most recent Alpine and Debian.
 *
 * @return list<string>
 */
function getQuickTestOperatingSystems(): array
{
    $all = DockerImages::getOperatingSystems();
    $alpine = array_values(array_filter($all, static fn (string $os): bool => str_starts_with($os, 'alpine')));
    $debian = array_values(array_diff($all, $alpine));

    return [$alpine[count($alpine) - 1], $debian[count($debian) - 1]];
}

/**
 * The handle of the supported extension (if any) of an argument like "xdebug" or "xdebug:^3.3".
 *
 * @param array<string, class-string<Extension>> $extensionClasses
 *
 * @return class-string<Extension>|null
 */
function getExtensionClass(string $argument, array $extensionClasses): ?string
{
    return $extensionClasses[explode(':', $argument, 2)[0]] ?? null;
}

/**
 * @param class-string<Extension> $class
 */
function supportsThreadSafety(string $class, string $phpVersion, bool $zts): bool
{
    if (!is_subclass_of($class, Extension\DeclaresThreadSafetySupport::class)) {
        return true;
    }
    $extension = ExtensionFactory::createForPHPVersion($class, $phpVersion);

    return $zts ? $extension->supportsZTS() : $extension->supportsNTS();
}

/**
 * The combinations of the option variants of the extensions.
 *
 * @param list<class-string<Extension>> $classes
 *
 * @return non-empty-list<list<string>>
 */
function getOptionVariants(array $classes): array
{
    $result = [[]];
    foreach ($classes as $class) {
        $variants = OPTION_VARIANTS[$class::getHandle()] ?? [[]];
        $combinations = [];
        foreach ($result as $options) {
            foreach ($variants as $variant) {
                $combinations[] = array_merge($options, $variant);
            }
        }
        $result = $combinations;
    }

    return $result;
}

/**
 * @param array<string, class-string<Extension>> $extensionClasses
 * @param list<string> $allPHPVersions sorted from the most recent
 * @param list<string> $operatingSystems
 *
 * @return list<array{extensions: string, options: list<string>, php: string, zts: bool, os: non-empty-list<string>}> os: the operating systems of the Docker images to be used
 */
function planTest(RequestedTest $test, array $extensionClasses, array $allPHPVersions, array $operatingSystems): array
{
    if ($test->phpVersions === null) {
        $phpVersions = $allPHPVersions;
    } else {
        echo '  - Requested PHP versions: ',implode(', ', $test->phpVersions), "\n";
        $phpVersions = array_values(array_intersect($allPHPVersions, $test->phpVersions));
    }
    $classes = [];
    foreach ($test->extensions as $extension) {
        $class = getExtensionClass($extension, $extensionClasses);
        if ($class === null) {
            echo "  - {$extension} is not supported by IPE: it may work with any PHP version\n";
        } else {
            echo "  - {$extension} supports PHP ", implode(', ', $class::getSupportedPHPVersions()), "\n";
            $classes[] = $class;
            $phpVersions = array_values(array_intersect($phpVersions, $class::getSupportedPHPVersions()));
        }
    }
    $optionVariants = getOptionVariants($classes);
    if (count($optionVariants) > 1) {
        echo "  - Tested with these options:\n";
        foreach ($optionVariants as $options) {
            echo '     - ', $options === [] ? '(default)' : implode(' ', $options), "\n";
        }
    }
    $result = [];
    foreach ($phpVersions as $phpVersion) {
        $zts = array_find([false, true], static fn (bool $zts): bool => array_all($classes, static fn (string $class): bool => supportsThreadSafety($class, $phpVersion, $zts)));
        if ($zts === null) {
            echo "  - PHP {$phpVersion}: skipped (no build of PHP supports all the extensions)\n";
            continue;
        }
        $imageOperatingSystems = array_values(array_intersect(array_map(static fn (DockerImage $image): string => $image->getOS(), DockerImages::find($phpVersion, $zts)), $operatingSystems));
        if ($imageOperatingSystems === []) {
            echo "  - PHP {$phpVersion}" . ($zts ? ' (ZTS)' : '') . ": skipped (no Docker image)\n";
            continue;
        }
        foreach ($optionVariants as $options) {
            $result[] = ['extensions' => implode(' ', $test->extensions), 'options' => $options, 'php' => $phpVersion, 'zts' => $zts, 'os' => $imageOperatingSystems];
        }
    }
    if ($result === []) {
        echo "  => no PHP version to be tested\n";
    } else {
        echo "  => to be tested on:\n";
        foreach ($result as $item) {
            echo "     - PHP {$item['php']}" . ($item['zts'] ? ' (ZTS)' : '') . ($item['options'] === [] ? '' : ' with ' . implode(' ', $item['options'])) . ': ' . implode(', ', $item['os']) . "\n";
        }
    }

    return $result;
}

/**
 * @throws \JsonException
 * @throws \RuntimeException
 */
function writeOutput(string $name, mixed $value): void
{
    $line = "{$name}=" . json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    $outputFile = getenv('GITHUB_OUTPUT');
    if (!is_string($outputFile) || $outputFile === '') {
        echo $line;
    } elseif (file_put_contents($outputFile, $line, FILE_APPEND) === false) {
        throw new \RuntimeException("Failed to write to {$outputFile}");
    }
}

/**
 * @param array<string, class-string<Extension>> $extensionClasses
 *
 * @throws \RuntimeException
 *
 * @return list<RequestedTest>|null NULL if the pull request must specify the extensions to be tested
 */
function collectTestsForPullRequest(string $base, string $head, array $extensionClasses): ?array
{
    $changedFiles = git(['diff', '--name-only', '--no-renames', "{$base}...{$head}"]);
    [$requestedTests, $stopFound] = collectTestsFromCommits($base, $head);
    if ($stopFound) {
        return $requestedTests;
    }
    $requestedTests = array_merge($requestedTests, collectTestsFromChanges($changedFiles, $extensionClasses));
    $filesRequiringTests = getFilesRequiringTests($changedFiles);
    // "Test: -STOP-" explicitly asks for no tests
    if ($requestedTests === [] && $filesRequiringTests !== []) {
        fwrite(STDERR, "These files affect install-php-extensions or its tests:\n- " . implode("\n- ", $filesRequiringTests) . "\n" . <<<'EOT'
            but no extension is going to be tested.
            Please specify at least one extension to be tested, for example by adding

            Test: zip

            to a commit of your pull request.

            See https://github.com/mlocati/install-php-extensions/blob/main/CONTRIBUTING.md#testing-extensions-in-pull-requests

            EOT);

        return null;
    }

    return $requestedTests;
}

/**
 * @param non-empty-list<string> $argv
 */
function main(array $argv): int
{
    $arguments = array_slice($argv, 1);
    $quick = ($arguments[0] ?? '') === '--quick';
    if ($quick) {
        array_shift($arguments);
    }
    $commits = count($arguments) === 2 && !in_array('', $arguments, true) ? [$arguments[0], $arguments[1]] : null;
    if ($commits === null && !($quick && $arguments === [])) {
        fwrite(STDERR, "Syntax:\n- {$argv[0]} <base commit> <head commit>\n- {$argv[0]} --quick [<commit before> <commit after>]\n");

        return 1;
    }
    try {
        $extensionClasses = [];
        foreach (\IPE\createApp()->make(Extension\Registry::class)->getExtensionClasses() as $class) {
            $extensionClasses[$class::getHandle()] = $class;
        }
        $allPHPVersions = array_values(array_unique(array_merge(...array_map(static fn (string $class): array => $class::getSupportedPHPVersions(), array_values($extensionClasses)))));
        usort($allPHPVersions, static fn (string $a, string $b): int => version_compare($b, $a));
        if ($commits !== null && !$quick) {
            $requestedTests = collectTestsForPullRequest($commits[0], $commits[1], $extensionClasses);
            if ($requestedTests === null) {
                return 1;
            }
            $operatingSystems = DockerImages::getOperatingSystems();
        } else {
            $requestedTests = isQuickTestNeeded($commits) ? [new RequestedTest([QUICK_TEST_EXTENSION], [QUICK_TEST_PHP_VERSION], 'quick test')] : [];
            $operatingSystems = getQuickTestOperatingSystems();
        }
        $tests = [];
        $planned = [];
        foreach ($requestedTests as $requestedTest) {
            $key = $requestedTest->getKey();
            // The most recent commits take precedence
            if (isset($planned[$key])) {
                echo "### {$key} (from {$requestedTest->source}): ignored, already requested by {$planned[$key]}\n";
                continue;
            }
            $planned[$key] = $requestedTest->source;
            echo "### {$key} (from {$requestedTest->source})\n";
            $tests = array_merge($tests, planTest($requestedTest, $extensionClasses, $allPHPVersions, $operatingSystems));
        }
        if ($tests === []) {
            echo "No extensions to be tested\n";
        }
        usort($tests, static fn (array $a, array $b): int => strcmp($a['extensions'], $b['extensions']) ?: version_compare($a['php'], $b['php']));
        $usedOperatingSystems = array_merge([], ...array_map(static fn (array $test): array => $test['os'], $tests));
        // Not the operating systems: they may be many, and the size of an environment variable is limited
        writeOutput('tests', array_map(static fn (array $test): array => ['extensions' => $test['extensions'], 'php' => $test['php'], 'zts' => $test['zts']] + ($test['options'] === [] ? [] : ['options' => $test['options']]), $tests));
        writeOutput('distros', array_values(array_intersect($operatingSystems, $usedOperatingSystems)));
    } catch (\Throwable $x) {
        fwrite(STDERR, rtrim((string) $x) . "\n");

        return 1;
    }

    return 0;
}

exit(main(Argv::getScriptArgv()));
