<?php

namespace IPE\Service;

use IPE\Extension;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemPackager;
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
        foreach (self::getExtensionPackageCandidates($this->packager, $phpPackage, $extension) as $packageName) {
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
     * Get the packages of other PHP versions that would be installed together with a package found by findExtensionPackage().
     * For example, php7.2-phalcon4 of the Ondřej Surý repositories depends on php-psr, which installs the most recent PHP.
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\SystemPackager
     *
     * @return list<non-empty-string>
     */
    public function getPackagesOfOtherPHPVersions(Package $package)
    {
        $phpPackage = $this->packager === null ? null : $this->getPackage();
        if ($this->packager === null || $phpPackage === null) {
            return [];
        }
        $result = [];
        $match = null;
        foreach (array_keys($this->packager->expandPackageList([$this->getInstallArgument($package)])) as $name) {
            // Like php8.5-cli (APT) or php85-common (APK)
            if (preg_match('/^(?<source>php\d+(?:\.\d+)?)(?:-|$)/', $name, $match) && $match['source'] !== $phpPackage->getSource()) {
                $result[] = $name;
            }
        }

        return $result;
    }

    /**
     * Get the names of the system packages that may provide an extension, in order of preference.
     *
     * @param \IPE\SystemPackager\Package $phpPackage the system package that installed PHP
     *
     * @return list<non-empty-string>
     */
    public static function getExtensionPackageCandidates(SystemPackager $packager, Package $phpPackage, Extension $extension)
    {
        $packageNames = $extension instanceof Extension\ProvidesSystemPackageNames ? $extension->getSystemPackageNames($phpPackage) : null;
        if ($packageNames === null) {
            $packageNames = [];
            foreach (array_unique(array_merge([$extension::getHandle()], $extension::getInstalledNames())) as $name) {
                $packageNames = array_merge($packageNames, $packager->getPHPExtensionPackageCandidates($phpPackage, $name));
            }
        }

        return array_values(array_unique($packageNames));
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
