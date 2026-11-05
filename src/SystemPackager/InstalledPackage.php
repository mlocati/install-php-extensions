<?php

namespace IPE\SystemPackager;

/**
 * A package installed by the system packager.
 */
final class InstalledPackage
{
    /**
     * @var non-empty-string
     */
    private $name;

    /**
     * @var non-empty-string
     */
    private $version;

    /**
     * @var non-empty-string
     */
    private $source;

    /**
     * @param non-empty-string $name
     * @param non-empty-string $version
     * @param non-empty-string $source
     */
    public function __construct($name, $version, $source)
    {
        $this->name = $name;
        $this->version = $version;
        $this->source = $source;
    }

    /**
     * @return non-empty-string
     *
     * @example 'php8.4-cli' (Debian, Ubuntu)
     * @example 'php84' (Alpine)
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return non-empty-string
     *
     * @example '8.4.27-1~deb13u1' (Debian, Ubuntu)
     * @example '8.4.27-r0' (Alpine)
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * The name of the source package this package has been built from.
     * It's the Source field for APT, and the origin for APK.
     * It's shared by all the packages built from the same source.
     *
     * @return non-empty-string
     *
     * @example 'php8.4' (Debian, Ubuntu: php8.4-cli, php8.4-dev, ... are built from it)
     * @example 'php84' (Alpine: php84, php84-dev, ... are built from it)
     */
    public function getSource()
    {
        return $this->source;
    }
}
