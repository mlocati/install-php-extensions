<?php

namespace IPE;

/**
 * Instances are built by the registry through the container, which requires detecting PHP and the operating system.
 * What's needed before that is available through static methods.
 */
abstract class Extension implements Extension\DeclaresIdentity
{
    /**
     * @public
     * @var string
     */
    const RX_VALID_HANDLE = '#^[a-z]([_]?[a-z0-9]+)*$#D';

    /**
     * @var \IPE\PHPInfo
     * @readonly
     */
    protected $php;

    /**
     * @var \IPE\OS
     * @readonly
     */
    protected $os;

    public function __construct(PHPInfo $php, OS $os)
    {
        $this->php = $php;
        $this->os = $os;
    }

    /**
     * @param string $version the version of the extension (empty for the latest one)
     * @param non-empty-string $minimum
     *
     * @return bool
     */
    protected static function isVersionAtLeast($version, $minimum)
    {
        return $version === '' || version_compare($version, $minimum) >= 0;
    }
}
