<?php

namespace IPE\Installer;

use IPE\Exception;
use IPE\SystemPackager;
use IPE\SystemPackager\Packages;

/**
 * Installs the system packages needed to compile and run the extensions, and removes the volatile ones afterwards.
 */
final class DependencyInstaller
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var \IPE\SystemPackager
     */
    private $packager;

    public function __construct(SystemPackager $packager, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->packager = $packager;
    }

    /**
     * Expand the package lists to the packages that will actually be installed, then install them.
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Installer\InstalledDependencies
     */
    public function install(Packages $required)
    {
        if ($required->isEmpty()) {
            return new InstalledDependencies();
        }
        $installedBefore = $this->packager->listInstalledPackages();
        $persistent = [];
        $alreadyInstalled = [];
        if ($required->getPersistent() !== []) {
            $persistent = array_keys($this->packager->expandPackageList($required->getPersistent()));
            $alreadyInstalled = self::findInstalled($required->getPersistent(), $installedBefore);
        }
        $volatile = [];
        if ($required->getVolatile() !== []) {
            // Expanded together with the persistent ones, otherwise a build dependency may pull in a package that conflicts with them.
            // Packages needed at runtime are never volatile, even if a build dependency pulls them in.
            $volatile = array_values(array_diff(
                array_keys($this->packager->expandPackageList(array_merge($required->getPersistent(), $required->getVolatile()))),
                $persistent
            ));
        }
        if ($alreadyInstalled !== []) {
            $this->logger->info('Marking as manually installed the already available packages: ' . implode(' ', $alreadyInstalled));
            $this->packager->markAsManuallyInstalled($alreadyInstalled);
        }
        $toInstall = array_merge($persistent, $volatile);
        if ($toInstall !== []) {
            $this->logger->info('Packages to be kept after the installation: ' . ($persistent === [] ? '(none)' : implode(' ', $persistent)));
            $this->logger->info('Packages to be used only for the installation: ' . ($volatile === [] ? '(none)' : implode(' ', $volatile)));
            try {
                $this->packager->installPackages($toInstall);
            } catch (Exception\SystemPackager $x) {
                // A partial installation may break the packager (apk refuses any later operation, for example)
                try {
                    $this->cleanup(new InstalledDependencies([], $toInstall, $installedBefore), false);
                } catch (\Exception $cleanupError) {
                    $this->logger->warning("Failed to remove the packages of the failed installation: {$cleanupError->getMessage()}");
                }
                throw $x;
            }
        }

        return new InstalledDependencies($persistent, $volatile, $toInstall === [] ? [] : $installedBefore);
    }

    /**
     * @param list<non-empty-string> $packages names of packages, or regular expressions if they start with ^
     * @param list<non-empty-string> $installed
     *
     * @return list<non-empty-string>
     */
    private static function findInstalled(array $packages, array $installed)
    {
        $result = [];
        foreach ($installed as $name) {
            foreach ($packages as $package) {
                if ($package[0] === '^' ? preg_match("#{$package}#", $name) === 1 : $package === $name) {
                    $result[] = $name;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Remove the volatile packages, restore the packages that their removal may have taken away, and optionally delete the package cache.
     *
     * @param bool $deleteCache
     *
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function cleanup(InstalledDependencies $installed, $deleteCache)
    {
        if ($installed->getVolatile() !== []) {
            $this->logger->info('Removing the packages used only for the installation: ' . implode(' ', $installed->getVolatile()));
            $this->packager->removePackages($installed->getVolatile());
            if ($installed->getPreviouslyInstalled() !== []) {
                $this->logger->info('Restoring the previously installed packages');
                $this->packager->restorePackages($installed->getPreviouslyInstalled());
            }
        }
        if ($deleteCache) {
            $this->logger->info('Deleting the system packager cache');
            $this->packager->deleteCache();
        }
    }
}
