<?php

namespace IPE\Installer\Report;

/**
 * A system library that has been compiled from its source code.
 */
final class CompiledLibrary implements \JsonSerializable
{
    /**
     * @var non-empty-string
     */
    private $handle;

    /**
     * @var string
     */
    private $version;

    /**
     * @var string
     */
    private $sourceURL;

    /**
     * @param non-empty-string $handle
     * @param string $version empty string if unknown
     * @param string $sourceURL
     */
    public function __construct($handle, $version, $sourceURL)
    {
        $this->handle = $handle;
        $this->version = $version;
        $this->sourceURL = $sourceURL;
    }

    /**
     * @return non-empty-string
     *
     * @example 'lib-aom'
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * @return string empty string if unknown
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getSourceURL()
    {
        return $this->sourceURL;
    }

    /**
     * {@inheritdoc}
     *
     * @see \JsonSerializable::jsonSerialize()
     *
     * @return array{handle: non-empty-string, version: string, sourceURL: string}
     */
    #[\ReturnTypeWillChange()]
    public function jsonSerialize()
    {
        return [
            'handle' => $this->handle,
            'version' => $this->version,
            'sourceURL' => $this->sourceURL,
        ];
    }
}
