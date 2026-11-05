<?php

namespace IPE\Service;

use IPE\Extension;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemPackager\Package;

/**
 * Detects the system package (APT, APK) that installed PHP.
 */
final class PHPSystemPackage
{
    /**
     * @var \IPE\PHPInfo
     */
    private $php;

    /**
     * @var \IPE\SystemPackager|null NULL if the OS doesn't have a system packager
     */
    private $packager;

    /**
     * @var \IPE\SystemPackager\Package|false|null false if not detected yet
     */
    private $package = false;

    public function __construct(PHPInfo $php, OS $os)
    {
        $this->php = $php;
        $this->packager = $os instanceof OS\Posix ? $os->getSystemPackager() : null;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\SystemPackager\Package|null NULL if PHP hasn't been installed by a system package
     */
    public function getPackage()
    {
        if ($this->package === false) {
            $this->package = $this->packager === null ? null : $this->packager->getInstalledPackageOwningFile($this->php->getPHPBin());
        }

        return $this->package;
    }

    /**
     * Get the package that provides phpize and php-config, pinned to the version of PHP: otherwise the packager may upgrade PHP.
     *
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if PHP hasn't been installed by a system package
     *
     * @example 'php8.4-dev=8.4.27-1~deb13u1' (Debian, Ubuntu)
     * @example 'php84-dev=8.4.27-r0' (Alpine)
     */
    public function getDevelopmentPackage()
    {
        if ($this->packager === null) {
            return '';
        }
        $package = $this->getPackage();
        if ($package === null) {
            return '';
        }

        return $this->packager->formatPackageNameWithVersion($this->packager->getPHPDevelopmentPackageName($package), $package->getVersion());
    }

    /**
     * Find the system package that provides an extension for the installed PHP.
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return \IPE\SystemPackager\Package|null NULL if PHP hasn't been installed by a system package, or if there's no package for the extension
     */
    public function findExtensionPackage(Extension $extension)
    {
        if ($this->packager === null) {
            return null;
        }
        $phpPackage = $this->getPackage();
        if ($phpPackage === null) {
            return null;
        }
        $packageNames = $extension instanceof Extension\ProvidesSystemPackageNames ? $extension->getSystemPackageNames($phpPackage) : null;
        if ($packageNames === null) {
            $packageNames = [];
            foreach (array_unique(array_merge([$extension::getHandle()], $extension::getInstalledNames())) as $name) {
                $packageNames = array_merge($packageNames, $this->packager->getPHPExtensionPackageCandidates($phpPackage, $name));
            }
        }
        foreach (array_unique($packageNames) as $packageName) {
            $package = $this->packager->findAvailablePackage($packageName);
            if ($package === null) {
                continue;
            }
            if ($package->getSource() === $phpPackage->getSource()) {
                // Other versions of the packages built with PHP would upgrade PHP
                return $package->getVersion() === $phpPackage->getVersion()
                    ? $package
                    : $this->packager->findAvailablePackage($package->getName(), $phpPackage->getVersion());
            }

            return $package;
        }

        return null;
    }

    /**
     * Get the version of the software in a package found by findExtensionPackage().
     *
     * @return string empty string if it's not recognized
     */
    public function getUpstreamVersion(Package $package)
    {
        return $this->packager === null ? '' : $this->packager->getUpstreamVersion($package->getVersion());
    }

    /**
     * Get the argument that installs a package found by findExtensionPackage().
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    public function getInstallArgument(Package $package)
    {
        if ($this->packager !== null) {
            $phpPackage = $this->getPackage();
            if ($phpPackage !== null && $package->getSource() === $phpPackage->getSource()) {
                return $this->packager->formatPackageNameWithVersion($package->getName(), $package->getVersion());
            }
        }

        return $package->getName();
    }
}
