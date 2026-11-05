<?php

namespace IPE\Extension;

use IPE\Exception;
use IPE\Extension;
use IPE\Option;
use IPE\SystemDependencies;

/**
 * An extension compiled from the source code published on GitHub (because it isn't available on PECL or as a Composer package, or those versions don't work).
 */
abstract class GitHubExtension extends Extension implements CompiledFromVendorArchive, RequiresSystemDependencies
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::getVendorArchiveVersion()
     */
    public function getVendorArchiveVersion($versionRequirements, Option\Values $options)
    {
        if ($versionRequirements === '') {
            return $this->getLatestGitHubVersion();
        }
        if (!preg_match('/^(\d+(\.\d+)*|[0-9a-f]{40})$/D', $versionRequirements)) {
            throw new Exception\UnsupportedExtension('The extension ' . static::getHandle() . " can only be installed by specifying an exact version number or a commit (you asked for '{$versionRequirements}')");
        }

        return $versionRequirements;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::getVendorArchiveURL()
     */
    public function getVendorArchiveURL($version, Option\Values $options)
    {
        return "https://github.com/{$this->getGitHubRepository()}/archive/{$this->getGitReference($version)}.zip";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::prepareVendorSource()
     */
    public function prepareVendorSource($rootDir, $version, Option\Values $options)
    {
        return $rootDir;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::getVendorConfigureArguments()
     */
    public function getVendorConfigureArguments($rootDir, $version, Option\Values $options)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return array_merge([SystemDependencies\unzip::HANDLE], $this->getSourceDependencies($version));
    }

    /**
     * @return non-empty-string
     *
     * @example 'owner/repository'
     */
    abstract protected function getGitHubRepository();

    /**
     * Get the version to be installed if the user didn't specify it.
     *
     * @return non-empty-string
     */
    abstract protected function getLatestGitHubVersion();

    /**
     * Get the handles of the system dependencies needed to compile the source code.
     *
     * @param string $version the extension version (empty string if unknown)
     *
     * @return list<non-empty-string>
     */
    protected function getSourceDependencies($version)
    {
        return [];
    }

    /**
     * @param non-empty-string $version
     *
     * @return non-empty-string the name of the tag, or the commit
     */
    protected function getGitReference($version)
    {
        return $version;
    }
}
