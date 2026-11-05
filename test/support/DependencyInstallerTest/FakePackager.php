<?php

namespace IPE\Test\Support\DependencyInstallerTest;

use IPE\Exception;
use IPE\OS;
use IPE\SystemPackager;

/**
 * A system packager that records the operations instead of performing them.
 */
final class FakePackager extends SystemPackager
{
    /**
     * @var list<non-empty-string>
     */
    public $installed;

    /**
     * @var array<non-empty-string, list<non-empty-string>> package => the packages that installing it would add
     */
    public $expansions;

    /**
     * @var list<string>
     */
    public $log = [];

    /**
     * @var bool
     */
    public $failInstall = false;

    /**
     * @var \IPE\SystemPackager\Package|null the package that owns every file
     */
    public $owningPackage;

    /**
     * @var array<non-empty-string, list<\IPE\SystemPackager\Package>> the packages available in the repositories, by the name that finds them (it may be a virtual package)
     */
    public $available = [];

    /**
     * @param list<non-empty-string> $installed
     * @param array<non-empty-string, list<non-empty-string>> $expansions
     */
    public function __construct(OS $os, array $installed, array $expansions)
    {
        parent::__construct($os);
        $this->installed = $installed;
        $this->expansions = $expansions;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getPhpizeRequirements()
     */
    public function getPhpizeRequirements()
    {
        return ['autoconf', 'gcc'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::formatPackageNameWithVersion()
     */
    public function formatPackageNameWithVersion($name, $version)
    {
        return "{$name}={$version}";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getPHPDevelopmentPackageName()
     */
    public function getPHPDevelopmentPackageName(SystemPackager\Package $phpPackage)
    {
        return "{$phpPackage->getSource()}-dev";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getPHPExtensionPackageCandidates()
     */
    public function getPHPExtensionPackageCandidates(SystemPackager\Package $phpPackage, $extensionName)
    {
        // Like APT
        $name = "{$phpPackage->getSource()}-" . str_replace('_', '-', strtolower($extensionName));

        return $this->validatePackageName($name, false) ? [$name] : [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doFindAvailablePackage()
     */
    protected function doFindAvailablePackage($name, $version)
    {
        $this->log[] = $version === '' ? "find {$name}" : "find {$name}={$version}";
        foreach (isset($this->available[$name]) ? $this->available[$name] : [] as $package) {
            if ($version === '' || $package->getVersion() === $version) {
                return $package;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getUpstreamVersion()
     */
    public function getUpstreamVersion($version)
    {
        return (string) preg_replace('/-.*$/', '', $version);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::refresh()
     */
    public function refresh()
    {
        $this->log[] = 'refresh';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::listInstalledPackages()
     */
    public function listInstalledPackages()
    {
        $this->log[] = 'list';

        return $this->installed;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getInstalledPackageOwningFile()
     */
    public function getInstalledPackageOwningFile($path)
    {
        return $this->owningPackage;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doExpandPackageList()
     */
    protected function doExpandPackageList(array $packages)
    {
        $this->log[] = 'expand ' . implode(' ', $packages);
        $result = [];
        foreach ($packages as $package) {
            foreach (isset($this->expansions[$package]) ? $this->expansions[$package] : [] as $expanded) {
                $result[$expanded] = '1.0';
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doInstallPackages()
     */
    protected function doInstallPackages(array $packages)
    {
        $this->log[] = 'install ' . implode(' ', $packages);
        if ($this->failInstall) {
            throw new Exception\SystemPackager('install failed');
        }
        foreach ($packages as $package) {
            list($name) = explode('=', $package, 2);
            if ($name !== '' && !in_array($name, $this->installed, true)) {
                $this->installed[] = $name;
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doMarkAsManuallyInstalled()
     */
    protected function doMarkAsManuallyInstalled(array $packages)
    {
        $this->log[] = 'mark ' . implode(' ', $packages);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doRemovePackages()
     */
    protected function doRemovePackages(array $packages)
    {
        $this->log[] = 'remove ' . implode(' ', $packages);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doRestorePackages()
     */
    protected function doRestorePackages(array $packages)
    {
        $this->log[] = 'restore ' . implode(' ', $packages);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doUpgradeInstalledPackages()
     */
    protected function doUpgradeInstalledPackages($simulate)
    {
        $this->log[] = 'upgrade';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doDeleteCache()
     */
    protected function doDeleteCache()
    {
        $this->log[] = 'delete-cache';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::validatePackageName()
     */
    protected function validatePackageName($name, $mayIncludeVersion)
    {
        return preg_match($mayIncludeVersion ? '/^(\^.+\$|[a-z0-9][a-z0-9+._-]*(=\S+)?)$/D' : '/^(\^.+\$|[a-z0-9][a-z0-9+._-]*)$/D', $name) === 1;
    }
}
