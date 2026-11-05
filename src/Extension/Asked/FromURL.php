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
     * @param string $url
     * @param string $subdir
     * @param array<string, string|true> $options
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    public function __construct($url, $subdir = '', array $options = [])
    {
        $this->url = Url::validate($url);
        $validSubdir = trim(Regex::replace('#[/\\\\]+#', '/', $subdir), '/');
        if (strpos("/{$validSubdir}/", '/../') !== false) {
            throw new Exception\InvalidArgument('$subdir must be a path inside the archive');
        }
        $this->subdir = $validSubdir;
        $this->options = $options;
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
}
