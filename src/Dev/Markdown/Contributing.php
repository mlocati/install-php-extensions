<?php

namespace IPE\Dev\Markdown;

/**
 * Updated by the CI when the main branch changes, since it describes how the CI works.
 *
 * @internal
 */
final class Contributing extends GeneratedDocument
{
    /**
     * @var non-empty-string
     * @public
     */
    const SECTION_TEST_DISTROS = 'TEST DISTROS';

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Dev\Markdown\GeneratedDocument::getFilename()
     */
    public function getFilename()
    {
        return 'CONTRIBUTING.md';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Dev\Markdown\GeneratedDocument::getGenerator()
     */
    public function getGenerator()
    {
        return 'scripts/update-contributing.php';
    }
}
