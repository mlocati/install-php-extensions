<?php

namespace IPE\Dev\Markdown;

/**
 * Updated when a new version is released, so that it describes the released code.
 *
 * @internal
 */
final class Readme extends GeneratedDocument
{
    /**
     * @var non-empty-string
     * @public
     */
    const SECTION_SUPPORTED_EXTENSIONS = 'SUPPORTED PHP EXTENSIONS TABLE';

    /**
     * @var non-empty-string
     * @public
     */
    const SECTION_GENERAL_CLI_OPTIONS = 'GENERAL CLI OPTIONS';

    /**
     * @var non-empty-string
     * @public
     */
    const SECTION_EXTENSION_NOTES = 'EXTENSION NOTES';

    /**
     * @var non-empty-string
     * @public
     */
    const SECTION_SYSTEM_DEPENDENCY_OPTIONS = 'SYSTEM DEPENDENCY OPTIONS';

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Dev\Markdown\GeneratedDocument::getFilename()
     */
    public function getFilename()
    {
        return 'README.md';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Dev\Markdown\GeneratedDocument::getGenerator()
     */
    public function getGenerator()
    {
        return 'scripts/update-readme.php';
    }
}
