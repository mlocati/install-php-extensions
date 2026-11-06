<?php

namespace IPE\Service;

use IPE\Exception;
use IPE\OS;
use IPE\PHPInfo;

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
     * @var \IPE\OS
     */
    private $os;

    /**
     * @var \IPE\SystemPackager\InstalledPackage|false|null false if not detected yet
     */
    private $package = false;

    public function __construct(PHPInfo $php, OS $os)
    {
        $this->php = $php;
        $this->os = $os;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\SystemPackager\InstalledPackage|null NULL if PHP hasn't been installed by a system package
     */
    public function getPackage()
    {
        if ($this->package === false) {
            $this->package = $this->os instanceof OS\Posix ? $this->os->getSystemPackager()->getInstalledPackageOwningFile($this->php->getPHPBin()) : null;
        }

        return $this->package;
    }

    /**
     * Get the package that provides phpize and php-config, pinned to the version of PHP: otherwise the packager may upgrade PHP.
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedOS
     *
     * @return string empty string if PHP hasn't been installed by a system package
     *
     * @example 'php8.4-dev=8.4.27-1~deb13u1' (Debian, Ubuntu)
     * @example 'php84-dev=8.4.27-r0' (Alpine)
     */
    public function getDevelopmentPackage()
    {
        $package = $this->getPackage();
        if ($package === null) {
            return '';
        }
        if ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu) {
            return "{$package->getSource()}-dev={$package->getVersion()}";
        }
        if ($this->os instanceof OS\Alpine) {
            return "{$package->getSource()}-dev={$package->getVersion()}";
        }

        throw new Exception\UnsupportedOS('Unable to determine the development package of PHP for ' . get_class($this->os));
    }
}
