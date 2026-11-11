<?php

declare(strict_types=1);

/*
 * Update the automatically generated sections of CONTRIBUTING.md:
 * - the distros that can be specified in the Test-Distro lines of the commit messages
 */

namespace IPE\Scripts\Contributing\Update;

use IPE\Dev\DockerImages;
use IPE\Dev\Markdown\Contributing;

set_error_handler(
    static function (int $errno, string $errstr, string $errfile = '', int $errline = 0): never {
        throw new \RuntimeException("Error {$errno}: {$errstr}" . ($errfile === '' ? '' : " in {$errfile}" . ($errline === 0 ? '' : " at line {$errline}")));
    },
    -1,
);

require_once dirname(__DIR__) . '/src/autoload.php';

/**
 * @return list<string>
 */
function generateTestDistros(): array
{
    $phpVersions = [];
    foreach (DockerImages::getAll() as $image) {
        $phpVersions[$image->getOS()][] = $image->getPHPVersion();
    }
    $lines = [
        '| Distro | PHP versions |',
        '|---|---|',
    ];
    foreach (DockerImages::getOperatingSystems() as $os) {
        $osPHPVersions = array_values(array_unique($phpVersions[$os] ?? []));
        usort($osPHPVersions, static fn (string $a, string $b): int => version_compare($a, $b));
        $lines[] = "| `{$os}` | " . implode(', ', $osPHPVersions) . ' |';
    }

    return $lines;
}

function main(): int
{
    try {
        $updated = new Contributing()->update([
            Contributing::SECTION_TEST_DISTROS => generateTestDistros(),
        ]);
        echo $updated ? "CONTRIBUTING.md updated\n" : "CONTRIBUTING.md is already up to date\n";
    } catch (\Throwable $x) {
        fwrite(STDERR, rtrim((string) $x) . "\n");

        return 1;
    }

    return 0;
}

exit(main());
