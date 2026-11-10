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
     * @var string
     */
    private $baseImage;

    /**
     * @var list<non-empty-string>
     */
    private $setupCommands;

    /**
     * @var string
     */
    private $phpBinary;

    /**
     * @param non-empty-string $name eg 'php:8.4-cli-alpine3.21'
     * @param non-empty-string $os eg 'alpine3.21'
     * @param non-empty-string $phpVersion major.minor
     * @param bool $zts
     * @param bool $rc
     * @param string $baseImage empty string to pull $name, otherwise $name is built locally from this image
     * @param list<non-empty-string> $setupCommands the shell commands that build the image from $baseImage
     * @param string $phpBinary empty string for 'php'
     */
    public function __construct($name, $os, $phpVersion, $zts, $rc, $baseImage = '', array $setupCommands = [], $phpBinary = '')
    {
        $this->name = $name;
        $this->os = $os;
        $this->phpVersion = $phpVersion;
        $this->zts = $zts;
        $this->rc = $rc;
        $this->baseImage = $baseImage;
        $this->setupCommands = $setupCommands;
        $this->phpBinary = $phpBinary;
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

    /**
     * @return string empty string if the image must be pulled, otherwise the image to build it from
     */
    public function getBaseImage()
    {
        return $this->baseImage;
    }

    /**
     * @return list<non-empty-string> the shell commands that build the image from getBaseImage()
     */
    public function getSetupCommands()
    {
        return $this->setupCommands;
    }

    /**
     * @return non-empty-string eg 'php' or 'php84'
     */
    public function getPHPBinary()
    {
        return $this->phpBinary === '' ? 'php' : $this->phpBinary;
    }

    /**
     * How many times the CI installs every extension in this image: when PHP is installed by the system packager, also with --system-packages=never.
     *
     * @return int
     */
    public function getInstallationsPerExtension()
    {
        return $this->baseImage === '' ? 1 : 2;
    }
}
