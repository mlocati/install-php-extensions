<?php

namespace IPE\Extension\Asked;

use IPE\Exception;
use IPE\Extension;
use IPE\Extension\Asked;

final class FromHandle implements Asked
{
    /**
     * @var non-empty-string
     */
    private $handle;

    /**
     * @var string
     */
    private $versionRequirements;

    /**
     * @var array<string, string|true>
     */
    private $options;

    /**
     * @param non-empty-string $handle
     * @param string $versionRequirements
     * @param array<string, string|true> $options
     */
    public function __construct($handle, $versionRequirements, array $options = [])
    {
        if (!preg_match(Extension::RX_VALID_HANDLE, $handle)) {
            throw new Exception\InvalidArgument('$handle must be the handle that identifies an extension');
        }
        $this->handle = $handle;
        $this->versionRequirements = $versionRequirements;
        $this->options = $options;
    }

    /**
     * @return non-empty-string
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * @return string
     */
    public function getVersionRequirements()
    {
        return $this->versionRequirements;
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
