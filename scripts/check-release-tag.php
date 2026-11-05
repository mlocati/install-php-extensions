<?php

declare(strict_types=1);

/*
 * Check the tag of a new release: it must be a valid version, greater than the ones of the existing tags.
 * Syntax: php scripts/check-release-tag.php <tag>
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
 * @param non-empty-list<string> $argv
 *
 * @throws \RuntimeException
 */
function main(array $argv): int
{
    if (count($argv) !== 2) {
        fwrite(STDERR, "Syntax: {$argv[0]} <tag>\n");

        return 1;
    }
    $tag = $argv[1];
    if (preg_match(PharBuilder::VERSION_REGEX, $tag, $matches) !== 1) {
        fwrite(STDERR, "Invalid tag: '{$tag}' (examples of valid tags: 1.2.3, 1.2.3-alpha, 1.2.3-beta2, 1.2.3-RC1)\n");

        return 1;
    }
    $existingVersions = getExistingVersions();
    if ($existingVersions !== [] && version_compare($tag, $existingVersions[0]) <= 0) {
        fwrite(STDERR, "The tag {$tag} must be greater than the existing tag {$existingVersions[0]}\n");

        return 1;
    }
    $prerelease = $matches['prerelease'] !== '';
    echo $prerelease ? "{$tag} is a valid pre-release\n" : "{$tag} is a valid release\n";
    $outputFile = getenv('GITHUB_OUTPUT');
    if (is_string($outputFile) && $outputFile !== '') {
        if (file_put_contents($outputFile, 'prerelease=' . ($prerelease ? 'true' : 'false') . "\n", FILE_APPEND) === false) {
            fwrite(STDERR, "Failed to write to {$outputFile}\n");

            return 1;
        }
    }

    return 0;
}

exit(main(Argv::getScriptArgv()));
