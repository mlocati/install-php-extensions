<?php

namespace IPE\Extension;

use IPE\Option;

/**
 * What the extensions receive when they are going to be compiled or installed.
 */
final class BuildContext
{
    /**
     * @var non-empty-string
     */
    private $sourceDirectory;

    /**
     * @var string
     */
    private $version;

    /**
     * @var \IPE\Option\Values
     */
    private $optionValues;

    /**
     * @param non-empty-string $sourceDirectory the directory containing config.m4 (or the root directory of the archive, for the hooks that say so)
     * @param string $version the extension version (empty string if unknown)
     */
    public function __construct($sourceDirectory, $version, Option\Values $optionValues)
    {
        $this->sourceDirectory = $sourceDirectory;
        $this->version = $version;
        $this->optionValues = $optionValues;
    }

    /**
     * Get the directory containing config.m4 (for the extensions bundled in PHP: <PHP source>/ext/<name>), or the root directory of the archive for the hooks that say so.
     *
     * @return non-empty-string
     */
    public function getSourceDirectory()
    {
        return $this->sourceDirectory;
    }

    /**
     * Get the extension version (empty string if unknown).
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return \IPE\Option\Values
     */
    public function getOptionValues()
    {
        return $this->optionValues;
    }
}
