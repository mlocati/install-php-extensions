<?php

declare(strict_types=1);

/*
 * Update the automatically generated sections of README.md:
 * - the table of the supported PHP extensions
 * - the options of the command line
 * - the notes about the extensions (from scripts/extension-notes and from the source code of the extensions)
 * - the options of the system dependencies
 */

namespace IPE\Scripts\Readme\Update;

use IPE\CLI\Argv;
use IPE\Dev\ExtensionFactory;
use IPE\Dev\Readme\DynamicSection;
use IPE\Extension;

set_error_handler(
    static function (int $errno, string $errstr, string $errfile = '', int $errline = 0): never {
        throw new \RuntimeException("Error {$errno}: {$errstr}" . ($errfile === '' ? '' : " in {$errfile}" . ($errline === 0 ? '' : " at line {$errline}")));
    },
    -1,
);

define('IPE_CLI_ROOT_DIR', dirname(__DIR__));

const README_PATH = IPE_CLI_ROOT_DIR . '/README.md';

const EXTENSION_NOTES_DIR = __DIR__ . '/extension-notes';

require_once IPE_CLI_ROOT_DIR . '/src/autoload.php';

/**
 * @return array<string, class-string<Extension>> the keys are the extension handles, sorted
 */
function getExtensionClasses(\IPE\Service\Container $app): array
{
    $result = [];
    foreach ($app->make(Extension\Registry::class)->getExtensionClasses() as $class) {
        $result[$class::getHandle()] = $class;
    }
    ksort($result, SORT_STRING);

    return $result;
}

/**
 * @param array<string, class-string<Extension>> $extensionClasses
 *
 * @return list<string> sorted from the most recent
 */
function getAllPHPVersions(array $extensionClasses): array
{
    $result = [];
    foreach ($extensionClasses as $class) {
        $result = array_merge($result, $class::getSupportedPHPVersions());
    }
    $result = array_values(array_unique($result));
    usort($result, static fn (string $a, string $b): int => version_compare($b, $a));

    return $result;
}

function escapeTableCell(string $text): string
{
    return str_replace(['|', "\n"], ['\\|', ' '], $text);
}

/**
 * Avoid that texts like "<name>" are rendered as HTML tags.
 */
function escapeText(string $text): string
{
    return str_replace(['<', '>'], ['&lt;', '&gt;'], $text);
}

/**
 * @param array<string, class-string<Extension>> $extensionClasses
 * @param array<string, list<string>> $notes
 * @param list<string> $phpVersions
 *
 * @return list<string>
 */
function generateExtensionsTable(array $extensionClasses, array $notes, array $phpVersions): array
{
    $lines = [
        '| Extension |' . implode('', array_map(static fn (string $phpVersion): string => " PHP {$phpVersion} |", $phpVersions)),
        '|:---:|' . str_repeat(':---:|', count($phpVersions)),
    ];
    foreach ($extensionClasses as $handle => $class) {
        $line = "| {$handle}";
        if (isset($notes[$handle])) {
            $line .= "[&#x24D8;](#{$handle})";
        }
        if (is_a($class, Extension\AvailableAsComposerPackage::class, true)) {
            // GitHub strips <small>
            $line .= '<br><sub>' . escapeTableCell(escapeText($class::getComposerPackageName())) . '</sub>';
        }
        $line .= ' |';
        $supportedPHPVersions = $class::getSupportedPHPVersions();
        foreach ($phpVersions as $phpVersion) {
            $line .= in_array($phpVersion, $supportedPHPVersions, true) ? ' &check; |' : '  |';
        }
        $lines[] = $line;
    }
    $lines[] = '';
    $lines[] = '*Number of supported extensions: ' . count($extensionClasses) . '*';

    return $lines;
}

/**
 * @return string the syntax of the option, without the leading dashes
 */
function getDefinitionSyntax(Argv\Option\Definition $definition): string
{
    $result = $definition->getName();
    if ($definition instanceof Argv\Option\Definition\ValueInList) {
        return $result . '[=]<' . implode('|', $definition->getAllowedValues()) . '>';
    }
    if ($definition instanceof Argv\Option\Definition\BoolValue) {
        return $result . '[=]<yes|no>';
    }
    if ($definition instanceof Argv\Option\Definition\Value) {
        return $result . '[=]value';
    }
    if ($definition instanceof Argv\Option\Definition\OptionalValue) {
        return $result . '[=value]';
    }

    return $result;
}

/**
 * @throws \JsonException
 */
function getDefinitionDefault(Argv\Option\Definition $definition): string
{
    if (!$definition instanceof Argv\Option\Definition\Value) {
        return '';
    }
    $default = $definition->getDefaultValue();
    if ($default === null || $default === '' || $default === []) {
        return '';
    }

    return is_string($default) ? $default : json_encode($default, JSON_THROW_ON_ERROR);
}

/**
 * @param list<string> $firstColumns
 * @param bool $defaultIsText true if $default is a description rather than a value
 */
function generateOptionRow(array $firstColumns, string $syntax, string $description, string $default, bool $defaultIsText = false): string
{
    $cells = $firstColumns;
    $cells[] = '`--' . $syntax . '`';
    $cells[] = escapeText($description);
    $cells[] = $default === '' ? '' : ($defaultIsText ? escapeText($default) : "`{$default}`");

    return '| ' . implode(' | ', array_map(escapeTableCell(...), $cells)) . ' |';
}

/**
 * @param Argv\Option\Definition[] $definitions
 *
 * @throws \JsonException
 *
 * @return list<string>
 */
function generateCLIOptions(array $definitions): array
{
    $lines = [
        '| Option | Description | Default |',
        '|---|---|---|',
    ];
    foreach ($definitions as $definition) {
        if (str_contains($definition->getName(), ':') || $definition instanceof Argv\Option\Definition\Hidden) {
            continue;
        }
        $defaultDescription = $definition->getDefaultDescription();
        if ($defaultDescription === '') {
            $lines[] = generateOptionRow([], getDefinitionSyntax($definition), $definition->getDescription(), getDefinitionDefault($definition));
        } else {
            $lines[] = generateOptionRow([], getDefinitionSyntax($definition), $definition->getDescription(), $defaultDescription, true);
        }
    }

    return $lines;
}

/**
 * The options of the system dependencies are the CLI options in the form <handle>:<option>.
 *
 * @param Argv\Option\Definition[] $definitions
 *
 * @throws \JsonException
 *
 * @return list<string>
 */
function generateSystemDependencyOptions(array $definitions): array
{
    $lines = [
        '| Dependency | Option | Description | Default |',
        '|---|---|---|---|',
    ];
    foreach ($definitions as $definition) {
        $chunks = explode(':', $definition->getName(), 2);
        if (count($chunks) === 2 && !$definition instanceof Argv\Option\Definition\Hidden) {
            $lines[] = generateOptionRow([$chunks[0]], getDefinitionSyntax($definition), $definition->getDescription(), getDefinitionDefault($definition));
        }
    }

    return $lines;
}

/**
 * @throws \RuntimeException
 */
function describeEnvironment(string $environment): string
{
    $osNames = ['alpine' => 'Alpine', 'debian' => 'Debian', 'ubuntu' => 'Ubuntu', 'windows' => 'Windows'];
    if (!preg_match('/^(?<os>[a-z]+)(?:(?<osVersion>\d+(?:\.\d+)*)(?:\.\.(?<osVersionTo>\d+(?:\.\d+)*))?)?(?:-php(?<php>\d+(?:\.\d+)?))?(?:-(?<threadSafety>nts|zts))?$/D', $environment, $match) || !isset($osNames[$match['os']])) {
        throw new \RuntimeException("Unrecognized environment: {$environment}");
    }
    $result = $osNames[$match['os']];
    if (($match['osVersion'] ?? '') !== '') {
        $result .= " {$match['osVersion']}";
        if (($match['osVersionTo'] ?? '') !== '') {
            $result .= " to {$match['osVersionTo']}";
        }
    }
    $php = trim(($match['php'] ?? '') . ' ' . strtoupper($match['threadSafety'] ?? ''));
    if ($php !== '') {
        $result .= " with PHP {$php}";
    }

    return $result;
}

/**
 * @param class-string<Extension> $class
 *
 * @throws \RuntimeException
 *
 * @return list<string>
 */
function describeUnavailability(string $class): array
{
    $items = describeThreadSafety($class);
    if (is_subclass_of($class, Extension\DeclaresUnsupportedEnvironments::class)) {
        foreach ($class::getUnsupportedEnvironments() as $environment => $reason) {
            $items[] = describeEnvironment($environment) . ' (' . escapeText($reason) . ')';
        }
    }
    switch (count($items)) {
        case 0:
            return [];
        case 1:
            return ["Not available on {$items[0]}"];
        default:
            return array_merge(['Not available on:', ''], array_map(static fn (string $item): string => "- {$item}", $items));
    }
}

/**
 * @param class-string<Extension> $class
 *
 * @return list<string>
 */
function describeExtensionOptions(string $class): array
{
    if (!is_subclass_of($class, Extension\DeclaresOptions::class)) {
        return [];
    }
    $options = $class::getOptions();
    if ($options === []) {
        return [];
    }
    $lines = [
        'Command line options:',
        '',
        '| Option | Description | Default |',
        '|---|---|---|',
    ];
    foreach ($options as $option) {
        $syntax = $class::getHandle() . ':' . $option->getName() . match ($option->needsValue()) {
            true => '[=]value',
            false => '',
            null => '[=value]',
        };
        $lines[] = generateOptionRow([], $syntax, $option->getDescription(), (string) $option->getDefault());
    }

    return $lines;
}

/**
 * supportsZTS() and supportsNTS() may depend on the PHP version: check them with every supported PHP version.
 *
 * @param class-string<Extension> $class
 *
 * @return list<string> the builds of PHP where the extension is not available
 */
function describeThreadSafety(string $class): array
{
    if (!is_subclass_of($class, Extension\DeclaresThreadSafetySupport::class)) {
        return [];
    }
    $supportedPHPVersions = $class::getSupportedPHPVersions();
    $withoutZTS = [];
    $withoutNTS = [];
    foreach ($supportedPHPVersions as $phpVersion) {
        $extension = ExtensionFactory::createForPHPVersion($class, $phpVersion);
        if (!$extension->supportsZTS()) {
            $withoutZTS[] = $phpVersion;
        }
        if (!$extension->supportsNTS()) {
            $withoutNTS[] = $phpVersion;
        }
    }
    $result = [];
    foreach (['thread-safe (ZTS)' => $withoutZTS, 'non-thread-safe (NTS)' => $withoutNTS] as $build => $phpVersions) {
        if ($phpVersions === $supportedPHPVersions) {
            $result[] = "{$build} builds of PHP";
        } elseif ($phpVersions !== []) {
            $result[] = "{$build} builds of PHP " . implode(', ', $phpVersions);
        }
    }

    return $result;
}

/**
 * @param array<string, class-string<Extension>> $extensionClasses
 *
 * @throws \RuntimeException
 *
 * @return array<string, list<string>> the keys are the handles of the extensions with notes
 */
function collectExtensionNotes(array $extensionClasses): array
{
    $result = [];
    foreach ($extensionClasses as $handle => $class) {
        $blocks = [];
        $notesFile = EXTENSION_NOTES_DIR . "/{$handle}.md";
        if (is_file($notesFile)) {
            $contents = file_get_contents($notesFile);
            if ($contents === false) {
                throw new \RuntimeException("Failed to read {$notesFile}");
            }
            $blocks[] = explode("\n", trim(str_replace("\r\n", "\n", $contents)));
        }
        $blocks[] = describeUnavailability($class);
        $blocks[] = describeExtensionOptions($class);
        $lines = [];
        foreach (array_filter($blocks) as $block) {
            if ($lines !== []) {
                $lines[] = '';
            }
            $lines = array_merge($lines, $block);
        }
        if ($lines !== []) {
            $result[$handle] = $lines;
        }
    }
    $unknownNotes = array_diff(array_map(static fn (string $path): string => basename($path, '.md'), glob(EXTENSION_NOTES_DIR . '/*.md') ?: []), array_keys($extensionClasses));
    if ($unknownNotes !== []) {
        throw new \RuntimeException('Notes for unknown extensions: ' . implode(', ', $unknownNotes));
    }

    return $result;
}

/**
 * @param array<string, list<string>> $notes
 *
 * @return list<string>
 */
function generateExtensionNotes(array $notes): array
{
    $lines = [];
    foreach ($notes as $handle => $noteLines) {
        if ($lines !== []) {
            $lines[] = '';
        }
        $lines[] = "### {$handle}";
        $lines[] = '';
        $lines = array_merge($lines, $noteLines);
    }

    return $lines;
}

function main(): int
{
    $rc = 0;
    try {
        $app = \IPE\createApp();
        $extensionClasses = getExtensionClasses($app);
        $notes = collectExtensionNotes($extensionClasses);
        $definitions = $app->build(Argv\InstallPHPExtensions::class)->getDefinitions();
        $readme = file_get_contents(README_PATH);
        if ($readme === false) {
            throw new \RuntimeException('Failed to read README.md');
        }
        $lines = explode("\n", rtrim(str_replace("\r\n", "\n", $readme), "\n"));
        $generatedSections = [
            DynamicSection::SECTION_SUPPORTED_EXTENSIONS => generateExtensionsTable($extensionClasses, $notes, getAllPHPVersions($extensionClasses)),
            DynamicSection::SECTION_GENERAL_CLI_OPTIONS => generateCLIOptions($definitions),
            DynamicSection::SECTION_EXTENSION_NOTES => generateExtensionNotes($notes),
            DynamicSection::SECTION_SYSTEM_DEPENDENCY_OPTIONS => generateSystemDependencyOptions($definitions),
        ];
        foreach ($generatedSections as $sectionIdentifier => $generatedLines) {
            $section = DynamicSection::extractFrom($sectionIdentifier, $lines);
            if ($section === null) {
                throw new \RuntimeException("Unable to find the section {$sectionIdentifier} in README.md");
            }
            $lines = $section->withGeneratedContent($generatedLines)->getReadmeLines();
        }
        $newReadme = implode("\n", $lines) . "\n";
        if ($newReadme === $readme) {
            echo "README.md is already up to date\n";
        } else {
            if (file_put_contents(README_PATH, $newReadme) === false) {
                throw new \RuntimeException('Failed to write README.md');
            }
            echo "README.md updated\n";
        }
    } catch (\RuntimeException $x) {
        fwrite(STDERR, $x->getMessage() . "\n");
        $rc = 1;
    } catch (\Throwable $x) {
        fwrite(STDERR, rtrim((string) $x) . "\n");
        $rc = 1;
    }

    return $rc;
}

exit(main());
