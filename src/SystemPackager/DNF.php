<?php

namespace IPE\SystemPackager;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\Service\Regex;
use IPE\SystemPackager;

/**
 * The packager of RHEL-like systems: the subclasses implement the differences between dnf4 (DNF\v4) and dnf5 (DNF\v5).
 */
abstract class DNF extends SystemPackager
{
    /**
     * @var non-empty-string
     */
    const URL_OPTIONAL_REPOSITORIES = 'https://github.com/mlocati/install-php-extensions#optional-repositories-on-enterprise-linux';

    /**
     * Get the class that handles the dnf command available on a system.
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return class-string<\IPE\SystemPackager\DNF>
     */
    public static function getImplementationClass(Filesystem $fs)
    {
        list($rc, $output) = $fs->run(Command::create('dnf', ['--version'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        $match = null;
        // dnf5 prints "dnf5 version 5.2.18.0", dnf4 prints "4.14.0"
        if ($rc === 0 && preg_match('/^(?:dnf\d+ version )?(?<major>\d+)\./', ltrim($output), $match)) {
            switch ($match['major']) {
                case '4':
                    return DNF\v4::class;
                case '5':
                    return DNF\v5::class;
            }
        }

        throw new Exception\SystemPackager("Unsupported dnf version:\n{$output}");
    }

    /**
     * Like the dependencies of the php-devel package.
     * The repositories of Enterprise Linux that are enabled by default don't provide re2c.
     *
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getPhpizeRequirements()
     */
    public function getPhpizeRequirements()
    {
        return [
            'autoconf',
            'file',
            'gcc',
            'gcc-c++',
            'glibc-devel',
            'make',
            'pkgconf-pkg-config',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::formatPackageNameWithVersion()
     */
    public function formatPackageNameWithVersion($name, $version)
    {
        return "{$name}-{$version}";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getCACertificates()
     */
    public function getCACertificates()
    {
        // Fedora 44 doesn't provide /etc/pki/tls/certs/ca-bundle.crt anymore
        return ['/etc/pki/ca-trust/extracted/pem/tls-ca-bundle.pem', 'ca-certificates'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getPHPDevelopmentPackageName()
     */
    public function getPHPDevelopmentPackageName(Package $phpPackage)
    {
        return "{$phpPackage->getSource()}-devel";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getPHPExtensionPackageCandidates()
     */
    public function getPHPExtensionPackageCandidates(Package $phpPackage, $extensionName)
    {
        $name = strtolower($extensionName);
        $result = [];
        // The PECL packages provide the name without "pecl-", even when their name includes the major version (for example, php-pecl-redis6 provides php-redis)
        foreach (["{$phpPackage->getSource()}-{$name}", "{$phpPackage->getSource()}-pecl-{$name}"] as $candidate) {
            if ($this->validatePackageName($candidate, false)) {
                $result[] = $candidate;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doFindAvailablePackage()
     */
    protected function doFindAvailablePackage($name, $version)
    {
        $packages = $this->queryAvailablePackages([$version === '' ? $name : "{$name}-{$version}"]);
        if ($packages !== []) {
            return $packages[0];
        }
        // A virtual package (for example, php-redis is provided by php-pecl-redis6)
        $providers = [];
        foreach ($this->queryAvailablePackages(['--whatprovides', $name]) as $package) {
            $providers[$package->getName()] = true;
        }
        if ($providers === []) {
            return null;
        }
        $provider = count($providers) === 1 ? key($providers) : $this->findProviderChosenByDnf($name, array_keys($providers));
        if ($provider === '' || $provider === $name) {
            return null;
        }
        $packages = $this->queryAvailablePackages([$version === '' ? $provider : "{$provider}-{$version}"]);

        return $packages === [] ? null : $packages[0];
    }

    /**
     * For example, with the modular repository of Remi php-redis is provided by php-pecl-redis6, and by php-pecl-redis5 of EPEL (built for another PHP version).
     *
     * @param non-empty-string $name
     * @param list<non-empty-string> $providers
     *
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if dnf can't install any of them
     */
    private function findProviderChosenByDnf($name, array $providers)
    {
        try {
            $transaction = $this->doExpandPackageList([$name]);
        } catch (Exception\SystemPackager $x) {
            return '';
        }
        $chosen = array_values(array_intersect($providers, array_keys($transaction)));

        return count($chosen) === 1 ? $chosen[0] : '';
    }

    /**
     * @param non-empty-list<non-empty-string> $arguments
     *
     * @throws \IPE\Exception\IO
     *
     * @return list<\IPE\SystemPackager\Package> the most recent version of every package, preferring the architecture of the system
     */
    private function queryAvailablePackages(array $arguments)
    {
        list($rc, $output) = $this->os->getFilesystem()->run($this->createDnfCommand(array_merge(['repoquery', '--quiet', '--available', '--latest-limit=1', '--queryformat=' . $this->getQueryFormat()], $arguments))->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        if ($rc !== 0) {
            return [];
        }
        $preferred = [];
        $others = [];
        $architecture = $this->os->getRawMachineArchitecture();
        $match = null;
        foreach (Regex::split('/[\r\n]+/', $output, -1, true) as $line) {
            if (!preg_match('/^(?<name>[^|\s]+)\|(?<epoch>\d*)\|(?<version>[^|\s]+)\|(?<release>[^|\s]+)\|(?<arch>[^|\s]+)\|(?<sourcerpm>[^|\s]+)$/D', trim($line), $match)) {
                continue;
            }
            // The repositories of Oracle Linux also provide the source packages
            if (in_array($match['arch'], ['src', 'nosrc'], true)) {
                continue;
            }
            $package = self::createPackage($match['name'], $match['epoch'], $match['version'], $match['release'], $match['sourcerpm']);
            if (in_array($match['arch'], [$architecture, 'noarch'], true)) {
                $preferred[] = $package;
            } else {
                // For example, the i686 packages on x86_64
                $others[] = $package;
            }
        }

        return array_merge($preferred, $others);
    }

    /**
     * Get the value of the --queryformat option of dnf repoquery: name|epoch|version|release|arch|sourcerpm.
     *
     * @return non-empty-string
     */
    abstract protected function getQueryFormat();

    /**
     * @param non-empty-string $name
     * @param string $epoch empty string or '(none)' if the package doesn't have an epoch
     * @param non-empty-string $version
     * @param non-empty-string $release
     * @param non-empty-string $sourceRpm eg 'php-8.3.19-1.el10.src.rpm'
     *
     * @return \IPE\SystemPackager\Package
     */
    protected static function createPackage($name, $epoch, $version, $release, $sourceRpm)
    {
        $match = null;
        // The name of the source RPM is followed by its version and its release
        $source = preg_match('/^(?<name>.+)-[^-]+-[^-]+\.(?:no)?src\.rpm$/D', $sourceRpm, $match) ? $match['name'] : $name;

        return new Package($name, self::formatVersion($epoch, "{$version}-{$release}"), $source);
    }

    /**
     * @param string $epoch empty string or '(none)' if the package doesn't have an epoch
     * @param non-empty-string $versionRelease
     *
     * @return non-empty-string
     *
     * @example '8.3.19-1.el10' for '0' and '8.3.19-1.el10'
     * @example '1:4.3-8.el9' for '1' and '4.3-8.el9'
     */
    protected static function formatVersion($epoch, $versionRelease)
    {
        return in_array($epoch, ['', '0', '(none)'], true) ? $versionRelease : "{$epoch}:{$versionRelease}";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getUpstreamVersion()
     */
    public function getUpstreamVersion($version)
    {
        $match = null;
        // Without the epoch and the release of the package
        if (!preg_match('/^(?:\d+:)?(?<upstream>\d[^\-]*)(?:-[^\-]+)?$/D', $version, $match)) {
            return '';
        }

        return $match['upstream'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::refresh()
     */
    public function refresh()
    {
        list($rc) = $this->os->getFilesystem()->run($this->createDnfCommand(['makecache'])->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('dnf makecache failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doInstallPackages()
     */
    protected function doInstallPackages(array $packages)
    {
        list($rc) = $this->os->getFilesystem()->run($this->createDnfCommand(array_merge(['install', '-y', '--setopt=install_weak_deps=False'], $packages))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('dnf install failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doUpgradeInstalledPackages()
     */
    protected function doUpgradeInstalledPackages($simulate)
    {
        if ($simulate) {
            list($rc) = $this->os->getFilesystem()->run($this->createDnfCommand(['check-upgrade'])->withOutput(Command::OUTPUT_SHOW));
            // 100 means that there are packages to be upgraded
            if ($rc !== 0 && $rc !== 100) {
                throw new Exception\SystemPackager('dnf check-upgrade failed');
            }

            return;
        }
        list($rc) = $this->os->getFilesystem()->run($this->createDnfCommand(['upgrade', '-y', '--setopt=install_weak_deps=False'])->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('dnf upgrade failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doExpandPackageList()
     */
    protected function doExpandPackageList(array $packages)
    {
        list($rc, $output) = $this->os->getFilesystem()->run($this->createDnfCommand(array_merge(['install', '--assumeno', '--setopt=install_weak_deps=False'], $packages)));
        // --assumeno makes dnf fail with "Operation aborted"
        if ($rc !== 0 && !preg_match('/^Operation aborted\b/m', $output)) {
            throw new Exception\SystemPackager(implode("\n", array_merge(['dnf failed to list the packages:', rtrim($output)], $this->explainMissingPackages($output))));
        }

        return self::parseTransactionTable($output);
    }

    /**
     * Explain where the packages that dnf can't find may be: IPE doesn't enable the optional repositories of Enterprise Linux.
     *
     * @param string $output the output of a failed dnf command
     *
     * @throws \IPE\Exception\IO
     *
     * @return list<non-empty-string>
     */
    private function explainMissingPackages($output)
    {
        $matches = null;
        if (!$this->os instanceof OS\Posix\RHELLike\EnterpriseLinux || !preg_match_all('/^No match for argument: (?<name>\S+)[ \t]*$/m', $output, $matches)) {
            return [];
        }
        $missing = array_values(array_unique($matches['name']));
        $result = [];
        foreach ($this->listRepositories(false) as $repository) {
            if (!preg_match('/^(?:crb|powertools)$|codeready/i', $repository) || preg_match('/debug|source/i', $repository)) {
                continue;
            }
            $found = [];
            foreach ($missing as $index => $name) {
                // The missing arguments may be capabilities, like pkgconfig(oniguruma)
                if ($this->queryAvailablePackages(["--repo={$repository}", '--whatprovides', $name]) !== []) {
                    $found[] = $name;
                    unset($missing[$index]);
                }
            }
            if ($found !== []) {
                $result[] = "The disabled repository {$repository} provides: " . implode(' ', $found);
            }
        }
        if ($missing !== [] && preg_grep('/epel/i', $this->listRepositories(true)) === []) {
            $result[] = 'EPEL is not enabled, and it may provide: ' . implode(' ', $missing);
        }
        if ($result !== []) {
            $result[] = 'See ' . self::URL_OPTIONAL_REPOSITORIES;
        }

        return $result;
    }

    /**
     * @param bool $enabled
     *
     * @throws \IPE\Exception\IO
     *
     * @return list<non-empty-string> the IDs of the enabled or disabled repositories
     */
    private function listRepositories($enabled)
    {
        list($rc, $output) = $this->os->getFilesystem()->run($this->createDnfCommand(['repolist', $enabled ? '--enabled' : '--disabled'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        if ($rc !== 0) {
            return [];
        }
        $result = [];
        $match = null;
        foreach (Regex::split('/[\r\n]+/', $output, -1, true) as $line) {
            if (!preg_match('/^repo id\s/', $line) && preg_match('/^(?<id>\S+)/', $line, $match)) {
                $result[] = $match['id'];
            }
        }

        return $result;
    }

    /**
     * Get the packages that a transaction would install.
     * Like with APT and APK, the upgraded packages are not included: otherwise they would be removed with the volatile ones (for example, libcurl-devel upgrades libcurl-minimal, which dnf needs).
     *
     * @param string $output the output of dnf install --assumeno
     *
     * @return array<non-empty-string, non-empty-string>
     */
    protected static function parseTransactionTable($output)
    {
        $result = [];
        $inInstallSection = false;
        $pendingName = '';
        $match = null;
        foreach (Regex::split('/\r?\n/', $output) as $line) {
            if (preg_match('/^\S.*:$/D', $line)) {
                // Like "Installing:", "Installing dependencies:", "Upgrading:", "Removing:", "Enabling module streams:"
                $inInstallSection = preg_match('/^Installing\b/', $line) === 1;
                $pendingName = '';
                continue;
            }
            if (!preg_match('/^\s/', $line)) {
                // Like the empty line or "Transaction Summary" after the table of dnf4
                $inInstallSection = false;
            }
            if (!$inInstallSection) {
                continue;
            }
            $fields = Regex::split('/\s+/', trim($line), -1, true);
            if ($pendingName !== '') {
                // A narrow terminal makes dnf4 print the long names in their own line
                array_unshift($fields, $pendingName);
                $pendingName = '';
            } elseif (count($fields) === 1) {
                $pendingName = $fields[0];
                continue;
            }
            // dnf5 prints the packages that are replaced (for example, the obsoleted ones), like "   replacing pcre2 x86_64 0:10.47-1.fc44 ..."
            if (count($fields) < 4 || $fields[0] === 'replacing' || !preg_match('/^(?:(?<epoch>\d+):)?(?<versionRelease>[^:\s]+-[^\s\-]+)$/D', $fields[2], $match)) {
                continue;
            }
            $result[$fields[0]] = self::formatVersion($match['epoch'], $match['versionRelease']);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::listInstalledPackages()
     */
    public function listInstalledPackages()
    {
        list($rc, $output) = $this->os->getFilesystem()->run(Command::create('rpm', ['--query', '--all', '--queryformat=%{NAME}\n'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('rpm --query --all failed');
        }
        $result = [];
        foreach (Regex::split('/[\r\n]+/', $output, -1, true) as $line) {
            $line = trim($line);
            // Some packages can be installed in more versions (like kernel and gpg-pubkey)
            if ($line !== '' && !in_array($line, $result, true)) {
                $result[] = $line;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getInstalledPackageOwningFile()
     */
    public function getInstalledPackageOwningFile($path)
    {
        list($rc, $output) = $this->os->getFilesystem()->run(Command::create('rpm', ['--query', '--file', '--queryformat=%{NAME}|%{EPOCH}|%{VERSION}|%{RELEASE}|%{SOURCERPM}\n', $path])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        $match = null;
        // Like "php-cli|(none)|8.0.30|8.el9_8.1|php-8.0.30-8.el9_8.1.src.rpm"
        if ($rc !== 0 || !preg_match('/^(?<name>[^|\s]+)\|(?<epoch>[^|\s]*)\|(?<version>[^|\s]+)\|(?<release>[^|\s]+)\|(?<sourcerpm>[^|\s]+)$/m', $output, $match)) {
            return null;
        }

        return self::createPackage($match['name'], $match['epoch'], $match['version'], $match['release'], $match['sourcerpm']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doRemovePackages()
     */
    protected function doRemovePackages(array $packages)
    {
        // dnf also removes the dependencies that are no longer needed
        list($rc) = $this->os->getFilesystem()->run($this->createDnfCommand(array_merge(['remove', '-y'], $packages))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('dnf remove failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doRestorePackages()
     */
    protected function doRestorePackages(array $packages)
    {
        // dnf install doesn't upgrade the installed packages, and some packages (like gpg-pubkey) are not in the repositories
        list($rc) = $this->os->getFilesystem()->run($this->createDnfCommand(array_merge(['install', '-y', '--setopt=install_weak_deps=False', $this->getSkipUnavailableOption()], $packages))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('dnf install failed');
        }
    }

    /**
     * Get the option that makes dnf install skip the packages that are not available.
     *
     * @return non-empty-string
     */
    abstract protected function getSkipUnavailableOption();

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doDeleteCache()
     */
    protected function doDeleteCache()
    {
        $this->os->getFilesystem()->run($this->createDnfCommand(['clean', 'all'])->withOutput(Command::OUTPUT_SHOW));
    }

    /**
     * @param list<string> $arguments
     *
     * @return \IPE\Filesystem\Command
     */
    protected function createDnfCommand(array $arguments)
    {
        return Command::create('dnf', $arguments);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::validatePackageName()
     * @see https://rpm-software-management.github.io/rpm/manual/spec.html
     */
    protected function validatePackageName($name, $mayIncludeVersion)
    {
        // Names of packages, or capabilities like pkgconfig(libzip)
        if (preg_match('/^[A-Za-z0-9_+][A-Za-z0-9_+.\-]*(?:\([A-Za-z0-9_+.\-:\/]+\))?$/D', $name)) {
            return true;
        }

        // Names followed by a version with an epoch, like make-1:4.3-8.el9
        return $mayIncludeVersion && preg_match('/^[A-Za-z0-9_+][A-Za-z0-9_+.\-]*-\d+:[A-Za-z0-9_+.~^]+-[A-Za-z0-9_+.~^]+$/D', $name) === 1;
    }
}
