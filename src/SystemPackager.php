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
     * @return \IPE\SystemPackager\InstalledPackage|null NULL if the file doesn't belong to any installed package
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
