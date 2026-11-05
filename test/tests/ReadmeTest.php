<?php

namespace IPE\Test;

use IPE\Dev\Git;
use IPE\Dev\Readme\DynamicSection;
use IPE\Test\Support\TestCase;

final class ReadmeTest extends TestCase
{
    /**
     * @var list<string>
     */
    private static $currentLines;

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Test\Support\TestCaseBase::doSetUpBeforeClass()
     */
    protected static function doSetUpBeforeClass()
    {
        $contents = file_get_contents(IPE_ROOT_DIR . '/README.md');
        if ($contents === false) {
            self::fail('Failed to read README.md');
        }
        self::$currentLines = self::splitLines($contents);
    }

    /**
     * @return void
     */
    public function testAllSectionsExist()
    {
        foreach (DynamicSection::getSectionIdentifiers() as $sectionIdentifier) {
            self::assertNotNull(DynamicSection::extractFrom($sectionIdentifier, self::$currentLines), "The section {$sectionIdentifier} is missing in README.md");
        }
    }

    /**
     * @return void
     */
    public function testNoUnknownSections()
    {
        $sectionIdentifiers = DynamicSection::getSectionIdentifiers();
        $unknownPlaceholders = [];
        foreach (self::$currentLines as $line) {
            if (preg_match('/^<!-- (?:START|END) OF (.+) -->$/D', $line, $matches) && !in_array($matches[1], $sectionIdentifiers, true)) {
                $unknownPlaceholders[] = $line;
            }
        }

        self::assertSame([], $unknownPlaceholders, 'README.md contains placeholders of unknown sections');
    }

    /**
     * The sections are updated by scripts/update-readme.php when creating a release: pull requests must not change them.
     * New sections must be added empty.
     *
     * @return void
     */
    public function testGeneratedSectionsAreUnchanged()
    {
        $base = (string) getenv('IPE_TEST_PULL_REQUEST_BASE');
        if ($base === '') {
            self::markTestSkipped('IPE_TEST_PULL_REQUEST_BASE is not set (in pull requests, only one CI job sets it)');
        }
        $result = Git::run(['show', "{$base}:README.md"]);
        self::assertNotNull($result, 'git is not available');
        list($rc, $output, $error) = $result;
        self::assertSame(0, $rc, "Failed to read README.md of the commit {$base}:\n" . trim($error));
        $baseLines = self::splitLines($output);
        foreach (DynamicSection::getSectionIdentifiers() as $sectionIdentifier) {
            $baseSection = DynamicSection::extractFrom($sectionIdentifier, $baseLines);
            $currentSection = DynamicSection::extractFrom($sectionIdentifier, self::$currentLines);
            self::assertNotNull($currentSection, "The section {$sectionIdentifier} is missing in README.md");
            self::assertSame(
                $baseSection === null ? [] : $baseSection->getSectionContentLines(),
                $currentSection->getSectionContentLines(),
                "Please don't update the section {$sectionIdentifier} of README.md: it will be updated automatically"
            );
        }
    }

    /**
     * @param string $contents
     *
     * @return list<string>
     */
    private static function splitLines($contents)
    {
        return explode("\n", str_replace("\r\n", "\n", $contents));
    }
}
