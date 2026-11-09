<?php

namespace IPE\Installer;

use IPE\OS;
use IPE\SystemPackager\Packages;

/**
 * Installs the CA certificates when they are missing (for example, in the debian and ubuntu Docker images).
 * They are kept after the installation: they are small, and the packages installed later may depend on them (for example, the Microsoft ODBC driver requires openssl).
 */
final class CACertificatesInstaller
{
    /**
     * @var \IPE\OS
     */
    private $os;

    /**
     * @var \IPE\Logger
     */
    private $logger;

    public function __construct(OS $os, \IPE\Logger $logger)
    {
        $this->os = $os;
        $this->logger = $logger;
    }

    /**
     * Install the CA certificates if they are missing.
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function install()
    {
        if (!$this->os instanceof OS\Posix) {
            return;
        }
        $packager = $this->os->getSystemPackager();
        $caCertificates = $packager->getCACertificates();
        if ($caCertificates === null) {
            return;
        }
        list($file, $package) = $caCertificates;
        if ($this->os->getFilesystem()->isFile($file)) {
            return;
        }
        $this->logger->info('Installing the CA certificates, needed to download over HTTPS');
        (new DependencyInstaller($packager, $this->logger))->install(new Packages([$package]));
    }
}
