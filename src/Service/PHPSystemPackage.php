<?php

namespace IPE\Service;

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
}
