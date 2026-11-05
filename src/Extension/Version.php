<?php

namespace IPE\Extension;

use IPE\Exception;
use IPE\Extension\Version\Stability;

abstract class Version
{
    /**
     * @var non-empty-string
     */
    private $version;

    /**
     * @var non-empty-string
     */
    private $stability;

    /**
     * @param non-empty-string $version
     * @param non-empty-string $stability
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    protected function __construct($version, $stability)
    {
        $this->version = $version;
        if (!Version\Stability::isValid($stability)) {
            throw new Exception\InvalidArgument("Invalid \$stability: {$stability}");
        }
        $this->stability = $stability;
    }

    /**
     * @return non-empty-string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return non-empty-string
     */
    public function getStability()
    {
        return $this->stability;
    }

    #[\ReturnTypeWillChange()]
    public function __toString()
    {
        return $this->stability === Stability::STABLE ? $this->version : "{$this->version} ({$this->stability})";
    }
}
