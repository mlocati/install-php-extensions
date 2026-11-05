<?php

namespace IPE\Extension\Manifest;

use IPE\Filesystem;
use IPE\Service\BasicXmlParser;

/**
 * Reads the extension metadata from the manifest files (composer.json, package2.xml, package.xml).
 */
final class Reader
{
    /**
     * @var \IPE\Extension\Manifest\Reader\Format[]
     */
    private $formats;

    /**
     * @param \IPE\Extension\Manifest\Reader\Format[] $formats the manifest formats, in order of preference (empty array: use the default ones)
     */
    public function __construct(\IPE\Logger $logger, array $formats = [])
    {
        if ($formats === []) {
            $formats = [
                new Reader\Format\ComposerJson($logger),
                new Reader\Format\PackageXml($logger, new BasicXmlParser()),
            ];
        }
        $this->formats = array_values($formats);
    }

    /**
     * @return \IPE\Extension\Manifest\Reader\Format[]
     */
    public function getFormats()
    {
        return $this->formats;
    }

    /**
     * Look for a manifest file in a directory containing the extension source code.
     *
     * @param non-empty-string $directory
     *
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Extension\Manifest|null
     */
    public function readFromDirectory($directory, Filesystem $fs)
    {
        foreach ($this->formats as $format) {
            $manifest = $format->readFromDirectory($directory, $fs);
            if ($manifest !== null) {
                return $manifest;
            }
        }

        return null;
    }

    /**
     * Read the manifest from the metadata of a version published in a Composer repository.
     *
     * @param array<mixed> $composerJson
     *
     * @return \IPE\Extension\Manifest|null
     */
    public function readFromComposerMetadata(array $composerJson)
    {
        foreach ($this->formats as $format) {
            if ($format instanceof Reader\Format\ComposerJson) {
                return $format->parseData($composerJson);
            }
        }

        return null;
    }
}
