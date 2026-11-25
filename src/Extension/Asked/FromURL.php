<?php

namespace IPE\Extension\Asked;

use IPE\Exception;
use IPE\Extension\Asked;
use IPE\Service\Regex;
use IPE\Service\Url;

final class FromURL implements Asked
{
    /**
     * @var non-empty-string
     */
    private $url;

    /**
     * @var string
     */
    private $subdir;

    /**
     * @var array<string, string|true>
     */
    private $options;

    /**
     * @var array<string, array<string, string|true>>
     */
    private $optionsByName;

    /**
     * @param string $url
     * @param string $subdir
     * @param array<string, string|true> $options
     * @param array<string, array<string, string|true>> $optionsByName the options of any extension, keyed by extension handle or Composer package name: the ones matching the manifest of the downloaded source code are added to $options
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    public function __construct($url, $subdir = '', array $options = [], array $optionsByName = [])
    {
        $this->url = Url::validate($url);
        $validSubdir = trim(Regex::replace('#[/\\\\]+#', '/', $subdir), '/');
        if (strpos("/{$validSubdir}/", '/../') !== false) {
            throw new Exception\InvalidArgument('$subdir must be a path inside the archive');
        }
        $this->subdir = $validSubdir;
        $this->options = $options;
        $this->optionsByName = $optionsByName;
    }

    /**
     * @return non-empty-string
     */
    public function getURL()
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getSubdir()
    {
        return $this->subdir;
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
