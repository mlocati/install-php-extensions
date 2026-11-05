<?php

namespace IPE;

/**
 * A repository engine that provides system packages.
 */
abstract class SystemPackager
{
    /**
     * @var bool
     */
    private $initialized = false;

    /**
     * @var \IPE\OS
     * @readonly
     */
    protected $os;

    protected function __construct(OS $os)
    {
        $this->os = $os;
    }

    /**
     * @return \IPE\OS
     */
    public function getOS()
    {
        return $this->os;
    }

    /**
     * @return list<non-empty-string>
     */
    public abstract function getPhpizeRequirements();

    /**
     * @param list<non-empty-string> $packages
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public final function expandPackageList(array $packages)
    {
        if ($packages === []) {
            return [];
        }
        foreach ($packages as $package) {
            if (!$this->validatePackageName($package, true)) {
                throw new Exception\InvalidArgument('Invalid $packages argument');
            }
        }
        $this->ensureInitialized();

        return $this->doExpandPackageList($packages);
    }

    /**
     * @param non-empty-list<non-empty-string> $packages
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return array<non-empty-string, non-empty-string>
     */
    protected abstract function doExpandPackageList(array $packages);

    /**
     * Find a package in the repositories.
     *
     * @param non-empty-string $name the name of a package, or of a virtual package provided by a single package
     * @param string $version empty string for the version that would be installed
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\SystemPackager\Package|null NULL if it's not available
     */
    public final function findAvailablePackage($name, $version = '')
    {
        if (!$this->validatePackageName($name, false)) {
            throw new Exception\InvalidArgument("Invalid package name: {$name}");
        }
        $this->ensureInitialized();

        return $this->doFindAvailablePackage($name, $version);
    }

    /**
     * @param non-empty-string $name
     * @param string $version
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\SystemPackager\Package|null
     */
    protected abstract function doFindAvailablePackage($name, $version);

    /**
     * Get the version of the packaged software, without the revision of the package.
     *
     * @param non-empty-string $version
     *
     * @return string empty string if it's not recognized
     *
     * @example '6.2.0' for '6.2.0-1' (Debian), '5.3.7' for '5.3.7+4.3.0-3ubuntu1' (Ubuntu), '6.3.0' for '6.3.0-r0' (Alpine)
     */
    public abstract function getUpstreamVersion($version);

    /**
     * Refresh the list of the available packages.
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public abstract function refresh();

    /**
     * Delete the package manager cache.
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public final function deleteCache()
    {
        $this->initialized = false;
        $this->doDeleteCache();
    }

    /**
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    protected abstract function doDeleteCache();

    /**
     * Get the argument that installs a specific version of a package.
     *
     * @param non-empty-string $name
     * @param non-empty-string $version
     *
     * @return non-empty-string
     */
    public abstract function formatPackageNameWithVersion($name, $version);

    /**
     * Get the name of the package that provides phpize and php-config for PHP installed by a system package.
     *
     * @param \IPE\SystemPackager\Package $phpPackage the package that installed PHP
     *
     * @return non-empty-string
     *
     * @example 'php8.4-dev' (APT)
     * @example 'php84-dev' (APK)
     */
    public abstract function getPHPDevelopmentPackageName(SystemPackager\Package $phpPackage);

    /**
     * Get the names of the packages that may provide an extension for PHP installed by a system package: they may not exist, or they may be virtual packages.
     *
     * @param \IPE\SystemPackager\Package $phpPackage the package that installed PHP
     * @param non-empty-string $extensionName the handle of the extension, or one of the names it's installed with
     *
     * @return list<non-empty-string> empty array if $extensionName can't be part of the name of a package
     *
     * @example ['php8.4-pdo-mysql'] (APT)
     * @example ['php84-redis', 'php84-pecl-redis'] (APK)
     */
    public abstract function getPHPExtensionPackageCandidates(SystemPackager\Package $phpPackage, $extensionName);

    /**
     * @param list<non-empty-string> $packages
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return void
     */
    public final function installPackages(array $packages)
    {
        if ($packages === []) {
            return;
        }
        foreach ($packages as $package) {
            if (!$this->validatePackageName($package, true)) {
                throw new Exception\InvalidArgument('Invalid $packages argument');
            }
        }
        $this->ensureInitialized();
        $this->doInstallPackages($packages);
    }

    /**
     * @param non-empty-list<non-empty-string> $packages
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return void
     */
    protected abstract function doInstallPackages(array $packages);

    /**
     * @param bool $simulate
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return void
     */
    public final function upgradeInstalledPackages($simulate = false)
    {
        $this->ensureInitialized();
        $this->doUpgradeInstalledPackages($simulate);
    }

    /**
     * @param bool $simulate
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    protected abstract function doUpgradeInstalledPackages($simulate);

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return void
     */
    protected function ensureInitialized()
    {
        if ($this->initialized) {
            return;
        }
        $this->refresh();
        $this->initialized = true;
    }

    /**
     * Get the names of the currently installed packages.
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return list<non-empty-string>
     */
    public abstract function listInstalledPackages();

    /**
     * Get the installed package that contains a file.
     *
     * @param non-empty-string $path the absolute path of the file
     *
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\SystemPackager\Package|null NULL if the file doesn't belong to any installed package
     */
    public abstract function getInstalledPackageOwningFile($path);

    /**
     * Mark already installed packages as explicitly required, so that they won't be removed as unused dependencies.
     *
     * @param list<non-empty-string> $packages
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return void
     */
    public final function markAsManuallyInstalled(array $packages)
    {
        if ($packages === []) {
            return;
        }
        $this->validatePackageNames($packages);
        $this->doMarkAsManuallyInstalled($packages);
    }

    /**
     * @param non-empty-list<non-empty-string> $packages
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    protected abstract function doMarkAsManuallyInstalled(array $packages);

    /**
     * Remove packages (and their configuration files).
     *
     * @param list<non-empty-string> $packages
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return void
     */
    public final function removePackages(array $packages)
    {
        if ($packages === []) {
            return;
        }
        $this->validatePackageNames($packages);
        $this->doRemovePackages($packages);
    }

    /**
     * @param non-empty-list<non-empty-string> $packages
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    protected abstract function doRemovePackages(array $packages);

    /**
     * Make sure that packages are installed, without upgrading them: used to restore the packages that the removal of other packages took away.
     * The package lists are not refreshed: call this after installPackages().
     *
     * @param list<non-empty-string> $packages
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return void
     */
    public final function restorePackages(array $packages)
    {
        if ($packages === []) {
            return;
        }
        $this->validatePackageNames($packages);
        $this->doRestorePackages($packages);
    }

    /**
     * @param non-empty-list<non-empty-string> $packages
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    protected abstract function doRestorePackages(array $packages);

    /**
     * @param non-empty-list<non-empty-string> $packages
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return void
     */
    private function validatePackageNames(array $packages)
    {
        foreach ($packages as $package) {
            if (!$this->validatePackageName($package, true)) {
                throw new Exception\InvalidArgument("Invalid package name: {$package}");
            }
        }
    }

    /**
     * @param non-empty-string $name
     * @param bool $mayIncludeVersion
     *
     * @return bool
     */
    protected abstract function validatePackageName($name, $mayIncludeVersion);
}
