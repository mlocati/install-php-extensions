<?php

namespace IPE\Extension\Asked;

use IPE\Exception;
use IPE\Extension\Asked;
use IPE\Extension\Version;

/**
 * An extension published as a Composer package.
 */
final class FromComposer implements Asked
{
    /**
     * @var non-empty-string
     */
    private $vendor;

    /**
     * @var non-empty-string
     */
    private $name;

    /**
     * @var string
     */
    private $versionRequirements;

    /**
     * @var array<string, string|true>
     */
    private $options;

    /**
     * @param string $fullName
     * @param string $versionRequirements
     * @param array<string, string|true> $options
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    public function __construct($fullName, $versionRequirements = '', array $options = [])
    {
        $vendorAndName = explode('/', $fullName);
        if (
            count($vendorAndName) !== 2
            || !preg_match(Version\Lister\Composer::RX_VALID_VENDOR, $vendorAndName[0])
            || !preg_match(Version\Lister\Composer::RX_VALID_NAME, $vendorAndName[1])
        ) {
            throw new Exception\InvalidArgument("'{$fullName}' is not a valid name of a Composer package");
        }
        $this->vendor = $vendorAndName[0];
        $this->name = $vendorAndName[1];
        $this->versionRequirements = $versionRequirements;
        $this->options = $options;
    }

    /**
     * @return non-empty-string
     */
    public function getVendor()
    {
        return $this->vendor;
    }

    /**
     * @return non-empty-string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return non-empty-string
     */
    public function getFullName()
    {
        return $this->vendor . '/' . $this->name;
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
