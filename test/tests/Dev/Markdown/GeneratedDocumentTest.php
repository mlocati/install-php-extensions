<?php

namespace IPE\Test\Dev\Markdown;

use IPE\Dev\Git;
use IPE\Dev\Markdown\DynamicSection;
use IPE\Dev\Markdown\GeneratedDocument;
use IPE\Test\Support\TestCase;

final class GeneratedDocumentTest extends TestCase
{
    /**
     * @return array<string, array{GeneratedDocument}>
     */
    public static function provideDocuments()
    {
        $result = [];
        foreach (GeneratedDocument::getAll() as $document) {
            $result[$document->getFilename()] = [$document];
        }

        return $result;
    }

    /**
     * @dataProvider provideDocuments
     *
     * @return void
     */
    public function testAllSectionsExist(GeneratedDocument $document)
    {
        $lines = $document->readLines();
        foreach ($document->getSectionIdentifiers() as $sectionIdentifier) {
            self::assertNotNull(DynamicSection::extractFrom($document, $sectionIdentifier, $lines), "The section {$sectionIdentifier} is missing in {$document->getFilename()}");
        }
    }

    /**
     * @dataProvider provideDocuments
     *
     * @return void
     */
    public function testNoUnknownSections(GeneratedDocument $document)
    {
        $sectionIdentifiers = $document->getSectionIdentifiers();
        $unknownPlaceholders = [];
        foreach ($document->readLines() as $line) {
            if (preg_match('/^<!-- (?:START|END) OF (.+) -->$/D', $line, $matches) && !in_array($matches[1], $sectionIdentifiers, true)) {
                $unknownPlaceholders[] = $line;
            }
        }

        self::assertSame([], $unknownPlaceholders, "{$document->getFilename()} contains placeholders of unknown sections");
    }

    /**
     * The sections are updated automatically: pull requests must not change them.
     * New sections must be added empty.
     *
     * @dataProvider provideDocuments
     *
     * @return void
     */
    public function testGeneratedSectionsAreUnchanged(GeneratedDocument $document)
    {
        $base = (string) getenv('IPE_TEST_PULL_REQUEST_BASE');
        if ($base === '') {
            self::markTestSkipped('IPE_TEST_PULL_REQUEST_BASE is not set (in pull requests, only one CI job sets it)');
        }
        $filename = $document->getFilename();
        $result = Git::run(['show', "{$base}:{$filename}"]);
        self::assertNotNull($result, 'git is not available');
        list($rc, $output, $error) = $result;
        self::assertSame(0, $rc, "Failed to read {$filename} of the commit {$base}:\n" . trim($error));
        $baseLines = GeneratedDocument::splitLines($output);
        $currentLines = $document->readLines();
        foreach ($document->getSectionIdentifiers() as $sectionIdentifier) {
            $baseSection = DynamicSection::extractFrom($document, $sectionIdentifier, $baseLines);
            $currentSection = DynamicSection::extractFrom($document, $sectionIdentifier, $currentLines);
            self::assertNotNull($currentSection, "The section {$sectionIdentifier} is missing in {$filename}");
            self::assertSame(
                $baseSection === null ? [] : $baseSection->getSectionContentLines(),
                $currentSection->getSectionContentLines(),
                "Please don't update the section {$sectionIdentifier} of {$filename}: it will be updated automatically"
            );
        }
    }
}
