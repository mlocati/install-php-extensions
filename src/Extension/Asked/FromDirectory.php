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
     * @param non-empty-string $path
     * @param array<string, string|true> $options
     */
    public function __construct($path, array $options = [])
    {
        $this->path = $path;
        $this->options = $options;
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
}
