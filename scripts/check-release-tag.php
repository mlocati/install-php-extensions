<?php

declare(strict_types=1);

/*
 * Check the tag of a new release: it must be a valid version, greater than the ones of the existing tags.
 * Without the tag (or with an empty one), the tag is the next patch version of the greatest existing tag.
 * Syntax: php scripts/check-release-tag.php [<tag>]
 * Writes TAG=<tag> to the file specified by the GITHUB_ENV environment variable (if set).
 * Writes prerelease=true or prerelease=false to the file specified by the GITHUB_OUTPUT environment variable (if set).
 */

namespace IPE\Scripts\CheckReleaseTag;

use IPE\CLI\Argv;
use IPE\CLI\PharBuilder;
use IPE\Dev\Git;

require_once __DIR__ . '/../src/autoload.php';

/**
 * @throws \RuntimeException
 *
 * @return list<string> sorted from the greatest to the smallest
 */
function getExistingVersions(): array
{
    $gitResult = Git::run(['ls-remote', '--tags', '--refs', 'origin']);
    if ($gitResult === null) {
        throw new \RuntimeException('git is not available');
    }
    [$rc, $output, $error] = $gitResult;
    if ($rc !== 0) {
        throw new \RuntimeException("Failed to list the tags of the repository:\n" . trim($error));
    }
    $result = [];
    foreach (explode("\n", str_replace("\r\n", "\n", $output)) as $line) {
        if (preg_match('_\trefs/tags/(.+)$_D', $line, $matches) === 1 && preg_match(PharBuilder::VERSION_REGEX, $matches[1]) === 1) {
            $result[] = $matches[1];
        }
    }
    usort($result, static fn (string $a, string $b): int => version_compare($b, $a));

    return $result;
}

/**
 * @param list<string> $existingVersions sorted from the greatest to the smallest
 *
 * @throws \RuntimeException
 */
function getNextPatchVersion(array $existingVersions): string
{
    if ($existingVersions === []) {
        throw new \RuntimeException('The repository has no tags: specify the tag of the release');
    }
    if (preg_match(PharBuilder::VERSION_REGEX, $existingVersions[0], $matches) !== 1 || $matches['prerelease'] !== '') {
        throw new \RuntimeException("The greatest existing tag ({$existingVersions[0]}) is not in the form <major>.<minor>.<patch>: specify the tag of the release");
    }

    return $matches['major'] . '.' . $matches['minor'] . '.' . ((int) $matches['patch'] + 1);
}

/**
 * @throws \RuntimeException
 */
function appendToGitHubFile(string $variable, string $line): void
{
    $file = getenv($variable);
    if (is_string($file) && $file !== '' && file_put_contents($file, "{$line}\n", FILE_APPEND) === false) {
        throw new \RuntimeException("Failed to write to {$file}");
    }
}

/**
 * @param non-empty-list<string> $argv
 */
function main(array $argv): int
{
    if (count($argv) > 2) {
        fwrite(STDERR, "Syntax: {$argv[0]} [<tag>]\n");

        return 1;
    }
    try {
        $existingVersions = getExistingVersions();
        $tag = $argv[1] ?? '';
        if ($tag === '') {
            $tag = getNextPatchVersion($existingVersions);
            echo "No tag specified: using {$tag}\n";
        }
        if (preg_match(PharBuilder::VERSION_REGEX, $tag, $matches) !== 1) {
            fwrite(STDERR, "Invalid tag: '{$tag}' (examples of valid tags: 1.2.3, 1.2.3-alpha, 1.2.3-beta2, 1.2.3-RC1)\n");

            return 1;
        }
        if ($existingVersions !== [] && version_compare($tag, $existingVersions[0]) <= 0) {
            fwrite(STDERR, "The tag {$tag} must be greater than the existing tag {$existingVersions[0]}\n");

            return 1;
        }
        $prerelease = $matches['prerelease'] !== '';
        echo $prerelease ? "{$tag} is a valid pre-release\n" : "{$tag} is a valid release\n";
        appendToGitHubFile('GITHUB_ENV', "TAG={$tag}");
        appendToGitHubFile('GITHUB_OUTPUT', 'prerelease=' . ($prerelease ? 'true' : 'false'));
    } catch (\RuntimeException $x) {
        fwrite(STDERR, $x->getMessage() . "\n");

        return 1;
    }

    return 0;
}

exit(main(Argv::getScriptArgv()));
