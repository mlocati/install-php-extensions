<?php

namespace IPE\Extension;

use IPE\Exception;
use IPE\Option;

/**
 * An asked extension after it has been resolved: its source code is in a local directory.
 */
final class Resolved
{
    /**
     * @public
     * @var string
     */
    const ORIGIN_PECL = 'pecl';

    /**
     * @public
     * @var string
     */
    const ORIGIN_COMPOSER = 'composer';

    /**
     * @public
     * @var string
     */
    const ORIGIN_BUNDLED = 'bundled';

    /**
     * @public
     * @var string
     */
    const ORIGIN_DIRECTORY = 'directory';

    /**
     * @public
     * @var string
     */
    const ORIGIN_URL = 'url';

    /**
     * @public
     * @var string
     */
    const ORIGIN_BINARY = 'binary';

    /**
     * @public
     * @var string
     */
    const ORIGIN_VENDOR_ARCHIVE = 'vendor archive';

    /**
     * @var \IPE\Extension|null
     */
    private $knownExtension;

    /**
     * @var non-empty-string
     */
    private $path;

    /**
     * @var string
     */
    private $version;

    /**
     * @var non-empty-string
     */
    private $origin;

    /**
     * @var \IPE\Extension\Manifest|null
     */
    private $manifest;

    /**
     * @var \IPE\Option\Values
     */
    private $optionValues;

    /**
     * @var bool
     */
    private $asked = true;

    /**
     * @var list<non-empty-string>
     */
    private $requiredBy = [];

    /**
     * @param \IPE\Extension|null $knownExtension
     * @param non-empty-string $path the directory containing the extension source code
     * @param string $version the extension version (empty string if unknown)
     * @param non-empty-string $origin one of the ORIGIN_* constants
     * @param \IPE\Extension\Manifest|null $manifest the manifest found in the source code directory (if any)
     * @param \IPE\Option\Values|null $optionValues the validated option values (null for no options)
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    public function __construct($knownExtension, $path, $version, $origin, $manifest = null, $optionValues = null)
    {
        if (!in_array($origin, self::getOrigins(), true)) {
            throw new Exception\InvalidArgument('Invalid $origin');
        }
        $this->knownExtension = $knownExtension;
        $this->path = $path;
        $this->version = $version;
        $this->origin = $origin;
        $this->manifest = $manifest;
        $this->optionValues = $optionValues === null ? new Option\Values() : $optionValues;
    }

    /**
     * @return non-empty-list<non-empty-string>
     */
    public static function getOrigins()
    {
        return [
            self::ORIGIN_PECL,
            self::ORIGIN_COMPOSER,
            self::ORIGIN_BUNDLED,
            self::ORIGIN_DIRECTORY,
            self::ORIGIN_URL,
            self::ORIGIN_BINARY,
            self::ORIGIN_VENDOR_ARCHIVE,
        ];
    }

    /**
     * Create a copy of this instance, with another source code directory and other option values.
     *
     * @param non-empty-string $path
     *
     * @return self
     */
    public function withSource($path, Option\Values $optionValues)
    {
        $result = clone $this;
        $result->path = $path;
        $result->optionValues = $optionValues;

        return $result;
    }

    /**
     * Create a copy of this instance, saying why it must be installed.
     *
     * @param bool $asked false if the extension must be installed only because other extensions require it
     * @param list<non-empty-string> $requiredBy the names of the extensions that require it
     *
     * @return self
     */
    public function withRequirers($asked, array $requiredBy)
    {
        $result = clone $this;
        $result->asked = $asked;
        $result->requiredBy = $requiredBy;

        return $result;
    }

    /**
     * Get the handle of the known extension, or the name declared in the manifest (empty string if unknown).
     *
     * @return string
     */
    public function getName()
    {
        $knownExtension = $this->knownExtension;
        if ($knownExtension !== null) {
            return $knownExtension::getHandle();
        }

        return $this->manifest === null ? '' : $this->manifest->getExtensionName();
    }

    /**
     * Has the extension been asked (false if it must be installed only because other extensions require it)?
     *
     * @return bool
     */
    public function isAsked()
    {
        return $this->asked;
    }

    /**
     * Get the names of the extensions that require this extension.
     *
     * @return list<non-empty-string>
     */
    public function getRequiredBy()
    {
        return $this->requiredBy;
    }

    /**
     * Get the extension known to IPE (null for unknown extensions).
     *
     * @return \IPE\Extension|null
     */
    public function getKnownExtension()
    {
        return $this->knownExtension;
    }

    /**
     * Get the directory containing the extension source code.
     *
     * @return non-empty-string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Get the extension version (empty string if unknown).
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return non-empty-string one of the ORIGIN_* constants
     */
    public function getOrigin()
    {
        return $this->origin;
    }

    /**
     * Get the manifest found in the source code directory (if any).
     *
     * @return \IPE\Extension\Manifest|null
     */
    public function getManifest()
    {
        return $this->manifest;
    }

    /**
     * @return \IPE\Option\Values
     */
    public function getOptionValues()
    {
        return $this->optionValues;
    }

    /**
     * Is this extension known with the specified handle or name?
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasName($name)
    {
        $names = $this->manifest === null ? [] : [$this->manifest->getExtensionName()];
        $knownExtension = $this->knownExtension;
        if ($knownExtension !== null) {
            $names = array_merge($names, [$knownExtension::getHandle()], $knownExtension::getInstalledNames());
        }
        foreach ($names as $candidate) {
            if (strcasecmp($candidate, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $result = $this->getName();
        if ($result === '') {
            $result = 'unknown extension';
        }
        if ($this->version !== '') {
            $result .= " {$this->version}";
        }
        switch ($this->origin) {
            case self::ORIGIN_DIRECTORY:
                return "{$result} (from {$this->path})";
            case self::ORIGIN_BUNDLED:
                return "{$result} (bundled in PHP)";
            default:
                return "{$result} (from {$this->origin})";
        }
    }
}
