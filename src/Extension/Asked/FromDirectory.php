<?php

namespace IPE\Extension\Asked;

use IPE\Extension\Asked;

final class FromDirectory implements Asked
{
    /**
     * @var non-empty-string
     */
    private $path;

    /**
     * @var array<string, string|true>
     */
    private $options;

    /**
     * @var array<string, array<string, string|true>>
     */
    private $optionsByName;

    /**
     * @param non-empty-string $path
     * @param array<string, string|true> $options
     * @param array<string, array<string, string|true>> $optionsByName the options of any extension, keyed by extension handle or Composer package name: the ones matching the manifest of the directory are added to $options
     */
    public function __construct($path, array $options = [], array $optionsByName = [])
    {
        $this->path = $path;
        $this->options = $options;
        $this->optionsByName = $optionsByName;
    }

    /**
     * @return non-empty-string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\Asked::getOptions()
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return array<string, array<string, string|true>> the keys are extension handles or Composer package names
     */
    public function getOptionsByName()
    {
        return $this->optionsByName;
    }
}
