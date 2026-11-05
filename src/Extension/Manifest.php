<?php

namespace IPE\Extension;

use IPE\Exception;
use IPE\Option\ConfigureOption;

/**
 * The metadata of an extension, as declared in its composer.json or package.xml file.
 */
final class Manifest
{
    /**
     * @public
     * @var string
     */
    const TYPE_COMPOSER = 'composer';

    /**
     * @public
     * @var string
     */
    const TYPE_PECL = 'pecl';

    /**
     * @public
     * @var int
     */
    const DEFAULT_PRIORITY = 80;

    /**
     * @var non-empty-string
     */
    private $type;

    /**
     * @var string
     */
    private $packageName;

    /**
     * @var string
     */
    private $extensionName;

    /**
     * @var string
     */
    private $version = '';

    /**
     * @var bool
     */
    private $zendExtension = false;

    /**
     * @var string
     */
    private $phpConstraint = '';

    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private $requiredExtensions = [];

    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private $optionalExtensions = [];

    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private $conflictingExtensions = [];

    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private $requiredLibraries = [];

    /**
     * @var bool
     */
    private $supportsZTS = true;

    /**
     * @var bool
     */
    private $supportsNTS = true;

    /**
     * @var int|null
     */
    private $priority;

    /**
     * @var string
     */
    private $buildPath = '';

    /**
     * @var string[]
     */
    private $osFamilies = [];

    /**
     * @var string[]
     */
    private $osFamiliesExclude = [];

    /**
     * @var array<string, \IPE\Option\ConfigureOption>
     */
    private $configureOptions = [];

    /**
     * @var array<mixed>
     */
    private $rawData = [];

    /**
     * @param non-empty-string $type one of the TYPE_* constants
     * @param string $packageName the name of the package ('vendor/name' for composer, the PECL name for PECL), empty if unknown
     * @param string $extensionName the name of the extension as seen by PHP, empty if unknown
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    public function __construct($type, $packageName, $extensionName)
    {
        if (!in_array($type, [self::TYPE_COMPOSER, self::TYPE_PECL], true)) {
            throw new Exception\InvalidArgument('Invalid $type');
        }
        $this->type = $type;
        $this->packageName = trim($packageName);
        $this->extensionName = trim($extensionName);
    }

    /**
     * @return non-empty-string one of the TYPE_* constants
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string empty if unknown
     */
    public function getPackageName()
    {
        return $this->packageName;
    }

    /**
     * @return string empty if unknown
     */
    public function getExtensionName()
    {
        return $this->extensionName;
    }

    /**
     * @return string empty if unknown
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param string $value empty if unknown
     *
     * @return $this
     */
    public function setVersion($value)
    {
        $this->version = trim($value);

        return $this;
    }

    /**
     * @return bool true for Zend extensions (loaded with zend_extension=), false for regular extensions (loaded with extension=)
     */
    public function isZendExtension()
    {
        return $this->zendExtension;
    }

    /**
     * @param bool $value
     *
     * @return $this
     */
    public function setZendExtension($value)
    {
        $this->zendExtension = $value;

        return $this;
    }

    /**
     * Get the supported PHP versions, as a composer version constraint.
     *
     * @return string empty if not specified
     */
    public function getPHPConstraint()
    {
        return $this->phpConstraint;
    }

    /**
     * @param string $value a composer version constraint, empty if not specified
     *
     * @return $this
     */
    public function setPHPConstraint($value)
    {
        $this->phpConstraint = trim($value);

        return $this;
    }

    /**
     * Get the extensions that must be installed, as an array whose keys are the extension names and whose values are composer version constraints.
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public function getRequiredExtensions()
    {
        return $this->requiredExtensions;
    }

    /**
     * @param string $name the name of the extension as seen by PHP
     * @param string $constraint a composer version constraint
     *
     * @return $this
     */
    public function addRequiredExtension($name, $constraint = '*')
    {
        $this->requiredExtensions[self::checkExtensionName($name)] = self::normalizeConstraint($constraint);

        return $this;
    }

    /**
     * Get the extensions that may be installed, as an array whose keys are the extension names and whose values are composer version constraints.
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public function getOptionalExtensions()
    {
        return $this->optionalExtensions;
    }

    /**
     * @param string $name the name of the extension as seen by PHP
     * @param string $constraint a composer version constraint
     *
     * @return $this
     */
    public function addOptionalExtension($name, $constraint = '*')
    {
        $this->optionalExtensions[self::checkExtensionName($name)] = self::normalizeConstraint($constraint);

        return $this;
    }

    /**
     * Get the extensions that can't be loaded with this one, as an array whose keys are the extension names and whose values are composer version constraints.
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public function getConflictingExtensions()
    {
        return $this->conflictingExtensions;
    }

    /**
     * @param string $name the name of the extension as seen by PHP
     * @param string $constraint a composer version constraint
     *
     * @return $this
     */
    public function addConflictingExtension($name, $constraint = '*')
    {
        $this->conflictingExtensions[self::checkExtensionName($name)] = self::normalizeConstraint($constraint);

        return $this;
    }

    /**
     * Get the system libraries that must be installed, as an array whose keys are the library handles (e.g. 'curl') and whose values are composer version constraints.
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public function getRequiredLibraries()
    {
        return $this->requiredLibraries;
    }

    /**
     * @param string $handle the library handle (e.g. 'curl' for composer's 'lib-curl')
     * @param string $constraint a composer version constraint
     *
     * @return $this
     */
    public function addRequiredLibrary($handle, $constraint = '*')
    {
        $this->requiredLibraries[self::checkExtensionName($handle)] = self::normalizeConstraint($constraint);

        return $this;
    }

    /**
     * @return bool
     */
    public function supportsZTS()
    {
        return $this->supportsZTS;
    }

    /**
     * @param bool $value
     *
     * @return $this
     */
    public function setSupportsZTS($value)
    {
        $this->supportsZTS = $value;

        return $this;
    }

    /**
     * @return bool
     */
    public function supportsNTS()
    {
        return $this->supportsNTS;
    }

    /**
     * @param bool $value
     *
     * @return $this
     */
    public function setSupportsNTS($value)
    {
        $this->supportsNTS = $value;

        return $this;
    }

    /**
     * Get the priority of the extension (prefix of the ini file name: lower values are loaded first).
     *
     * @return int|null NULL if not declared
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param int $value between 10 and 99
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return $this
     */
    public function setPriority($value)
    {
        if ($value < 10 || $value > 99) {
            throw new Exception\InvalidArgument('$value must be an integer between 10 and 99');
        }
        $this->priority = $value;

        return $this;
    }

    /**
     * Get the path (relative to the package root) of the directory containing the extension source code.
     *
     * @return string empty if the source code is in the package root
     */
    public function getBuildPath()
    {
        return $this->buildPath;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setBuildPath($value)
    {
        $this->buildPath = trim(str_replace('\\', '/', $value), '/');

        return $this;
    }

    /**
     * Get the OS families the extension is compatible with (empty: no restriction).
     *
     * @return string[]
     */
    public function getOSFamilies()
    {
        return $this->osFamilies;
    }

    /**
     * @param string[] $value
     *
     * @return $this
     */
    public function setOSFamilies(array $value)
    {
        $this->osFamilies = self::normalizeStringList($value);

        return $this;
    }

    /**
     * Get the OS families the extension is not compatible with.
     *
     * @return string[]
     */
    public function getOSFamiliesExclude()
    {
        return $this->osFamiliesExclude;
    }

    /**
     * @param string[] $value
     *
     * @return $this
     */
    public function setOSFamiliesExclude(array $value)
    {
        $this->osFamiliesExclude = self::normalizeStringList($value);

        return $this;
    }

    /**
     * @return \IPE\Option\ConfigureOption[]
     */
    public function getConfigureOptions()
    {
        return array_values($this->configureOptions);
    }

    /**
     * @param string $name
     *
     * @return \IPE\Option\ConfigureOption|null
     */
    public function getConfigureOption($name)
    {
        $name = ltrim($name, '-');

        return isset($this->configureOptions[$name]) ? $this->configureOptions[$name] : null;
    }

    /**
     * Add a configure option (replacing any option with the same name).
     *
     * @return $this
     */
    public function addConfigureOption(ConfigureOption $option)
    {
        $this->configureOptions[$option->getName()] = $option;

        return $this;
    }

    /**
     * Get the decoded contents of the manifest file.
     *
     * @return array<mixed>
     */
    public function getRawData()
    {
        return $this->rawData;
    }

    /**
     * @param array<mixed> $value
     *
     * @return $this
     */
    public function setRawData(array $value)
    {
        $this->rawData = $value;

        return $this;
    }

    /**
     * @param string $name
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return non-empty-string
     */
    private static function checkExtensionName($name)
    {
        $name = trim($name);
        if ($name === '') {
            throw new Exception\InvalidArgument('Invalid extension/library name');
        }

        return $name;
    }

    /**
     * @param string $constraint
     *
     * @return non-empty-string
     */
    private static function normalizeConstraint($constraint)
    {
        $constraint = trim($constraint);

        return $constraint === '' ? '*' : $constraint;
    }

    /**
     * @param array<mixed> $list
     *
     * @return string[]
     */
    private static function normalizeStringList(array $list)
    {
        $result = [];
        foreach ($list as $item) {
            $item = is_string($item) ? strtolower(trim($item)) : '';
            if ($item !== '' && !in_array($item, $result, true)) {
                $result[] = $item;
            }
        }

        return $result;
    }
}
