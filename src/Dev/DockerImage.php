<?php

namespace IPE\Dev;

/**
 * A Docker image where install-php-extensions can be tested.
 *
 * @internal
 */
final class DockerImage
{
    /**
     * @var non-empty-string
     */
    private $name;

    /**
     * @var non-empty-string
     */
    private $os;

    /**
     * @var non-empty-string
     */
    private $phpVersion;

    /**
     * @var bool
     */
    private $zts;

    /**
     * @var bool
     */
    private $rc;

    /**
     * @param non-empty-string $name eg 'php:8.4-cli-alpine3.21'
     * @param non-empty-string $os eg 'alpine3.21'
     * @param non-empty-string $phpVersion major.minor
     * @param bool $zts
     * @param bool $rc
     */
    public function __construct($name, $os, $phpVersion, $zts, $rc)
    {
        $this->name = $name;
        $this->os = $os;
        $this->phpVersion = $phpVersion;
        $this->zts = $zts;
        $this->rc = $rc;
    }

    /**
     * @return non-empty-string eg 'php:8.4-cli-alpine3.21'
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return non-empty-string eg 'alpine3.21'
     */
    public function getOS()
    {
        return $this->os;
    }

    /**
     * @return non-empty-string major.minor
     */
    public function getPHPVersion()
    {
        return $this->phpVersion;
    }

    /**
     * @return bool true for thread-safe builds of PHP
     */
    public function isZTS()
    {
        return $this->zts;
    }

    /**
     * @return bool true if the PHP version has not been released yet
     */
    public function isRC()
    {
        return $this->rc;
    }
}
