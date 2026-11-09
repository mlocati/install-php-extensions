<?php

namespace IPE\Installer;

use IPE\OS;
use IPE\SystemPackager\Packages;

/**
 * Installs the CA certificates until the shutdown of the state when they are missing (for example, in the debian and ubuntu Docker images).
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
     * Install the CA certificates as volatile packages if they are missing, and remove them when the state shuts down.
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function install(State $state)
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
        $dependencyInstaller = new DependencyInstaller($packager, $this->logger);
        $installed = $dependencyInstaller->install(new Packages([], [$package]));
        $state->registerShutdownCallback(static function () use ($dependencyInstaller, $installed) {
            $dependencyInstaller->cleanup($installed, false);
        });
    }
}
