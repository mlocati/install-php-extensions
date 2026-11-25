<?php

namespace IPE\Dev;

/**
 * A Homebrew formula of PHP, installed on a macOS runner of GitHub Actions.
 *
 * @internal
 */
final class HomebrewFormula implements TestEnvironment
{
    /**
     * @var non-empty-string
     */
    const OS = 'macos';

    /**
     * @var non-empty-string
     */
    private $name;

    /**
     * @var non-empty-string
     */
    private $phpVersion;

    /**
     * @param non-empty-string $name eg 'php@8.4'
     * @param non-empty-string $phpVersion major.minor
     */
    public function __construct($name, $phpVersion)
    {
        $this->name = $name;
        $this->phpVersion = $phpVersion;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Dev\TestEnvironment::getName()
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Dev\TestEnvironment::getOS()
     */
    public function getOS()
    {
        return self::OS;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Dev\TestEnvironment::getPHPVersion()
     */
    public function getPHPVersion()
    {
        return $this->phpVersion;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Dev\TestEnvironment::isZTS()
     */
    public function isZTS()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * The PHP formulae include their extensions: the other ones are always compiled.
     *
     * @see \IPE\Dev\TestEnvironment::getInstallationsPerExtension()
     */
    public function getInstallationsPerExtension()
    {
        return 1;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Dev\TestEnvironment::getRunsOn()
     */
    public function getRunsOn()
    {
        return 'macos-latest';
    }
}
