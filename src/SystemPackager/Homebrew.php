<?php

namespace IPE\SystemPackager;

use IPE\Exception;
use IPE\Filesystem\Command;
use IPE\Service\Regex;
use IPE\SystemPackager;

/**
 * Homebrew formulae include the headers too: there are no separate development packages.
 */
final class Homebrew extends SystemPackager
{
    /**
     * @var string|null
     */
    private $brew = null;

    /**
     * @var string|null
     */
    private $cellar = null;

    /**
     * @var string|null
     */
    private $prefix = null;

    /**
     * {@inheritdoc}
     *
     * Running brew update would bring newer formulae, and installing them would upgrade the installed dependencies (PHP included).
     *
     * @see \IPE\SystemPackager::refresh()
     */
    public function refresh()
    {
    }

    /**
     * {@inheritdoc}
     *
     * The compiler and make come from the Xcode Command Line Tools, which Homebrew can't install.
     *
     * @see \IPE\SystemPackager::getPhpizeRequirements()
     */
    public function getPhpizeRequirements()
    {
        list($rc) = $this->os->getFilesystem()->run(Command::create('xcode-select', ['--print-path'])->withOutput(Command::OUTPUT_DISCARD));
        if ($rc !== 0) {
            throw new Exception\SystemPackager("Compiling PHP extensions requires the Xcode Command Line Tools: you can install them with\nxcode-select --install");
        }

        return [
            'autoconf',
            'pkgconf',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * The compiler of Apple doesn't look in /opt/homebrew, and pkg-config doesn't look in the keg-only formulae (like zlib), which aren't linked in the prefix.
     *
     * @see \IPE\SystemPackager::getBuildEnvironment()
     */
    public function getBuildEnvironment(array $packages)
    {
        $prefix = $this->getPrefix();
        if ($prefix === '') {
            return [];
        }
        $result = [];
        $prefixes = [];
        foreach ($this->getKegOnlyFormulae($packages) as $name) {
            $prefixes[] = $this->getFormulaPrefix($name);
        }
        $pkgConfigPaths = [];
        foreach ($prefixes as $formulaPrefix) {
            $pkgConfigPaths[] = "{$formulaPrefix}/lib/pkgconfig";
        }
        if ($pkgConfigPaths !== []) {
            $result['PKG_CONFIG_PATH'] = implode(':', $pkgConfigPaths);
        }
        $prefixes[] = $prefix;
        $cppFlags = [];
        $ldFlags = [];
        foreach ($prefixes as $includedPrefix) {
            $cppFlags[] = "-I{$includedPrefix}/include";
            $ldFlags[] = "-L{$includedPrefix}/lib";
        }
        $result['CPPFLAGS'] = implode(' ', $cppFlags);
        $result['LDFLAGS'] = implode(' ', $ldFlags);

        return $result;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if Homebrew is not installed
     *
     * @example '/opt/homebrew'
     */
    public function getPrefix()
    {
        if ($this->prefix === null) {
            $this->prefix = '';
            $brew = $this->findBrew();
            if ($brew !== '') {
                list($rc, $output) = $this->os->getFilesystem()->run(self::createBrewCommand($brew, ['--prefix'], Command::OUTPUT_CAPTURE_STDOUT));
                if ($rc === 0) {
                    $this->prefix = rtrim(trim($output), '/');
                }
            }
        }

        return $this->prefix;
    }

    /**
     * The opt directory of a formula, which exists also for the keg-only formulae.
     *
     * @param non-empty-string $name
     *
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if Homebrew is not installed
     *
     * @example '/opt/homebrew/opt/php@8.4' for 'shivammathur/php/php@8.4'
     */
    public function getFormulaPrefix($name)
    {
        $prefix = $this->getPrefix();

        return $prefix === '' ? '' : "{$prefix}/opt/" . self::getShortName($name);
    }

    /**
     * {@inheritdoc}
     *
     * Homebrew only installs the current version of a formula.
     *
     * @see \IPE\SystemPackager::formatPackageNameWithVersion()
     */
    public function formatPackageNameWithVersion($name, $version)
    {
        return $name;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getCACertificates()
     */
    public function getCACertificates()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * The PHP formulae include phpize and php-config.
     *
     * @see \IPE\SystemPackager::getPHPDevelopmentPackageName()
     */
    public function getPHPDevelopmentPackageName(Package $phpPackage)
    {
        return $phpPackage->getName();
    }

    /**
     * {@inheritdoc}
     *
     * The PHP formulae of Homebrew include the extensions they provide.
     *
     * @see \IPE\SystemPackager::getPHPExtensionPackageCandidates()
     */
    public function getPHPExtensionPackageCandidates(Package $phpPackage, $extensionName)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doInstallPackages()
     */
    protected function doInstallPackages(array $packages)
    {
        list($rc) = $this->runBrew(array_merge(['install', '--formula'], $packages), Command::OUTPUT_SHOW);
        if ($rc !== 0) {
            throw new Exception\SystemPackager('brew install failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doFindAvailablePackage()
     */
    protected function doFindAvailablePackage($name, $version)
    {
        foreach ($this->getFormulaVersions([$name]) as $foundName => $foundVersion) {
            return $version === '' || $version === $foundVersion ? new Package($foundName, $foundVersion, $foundName) : null;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getUpstreamVersion()
     *
     * @example '8.4.14' for '8.4.14_1'
     */
    public function getUpstreamVersion($version)
    {
        $match = null;

        return preg_match('/^(?<upstream>\d[^_]*)(_\d+)?$/D', $version, $match) ? $match['upstream'] : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doUpgradeInstalledPackages()
     */
    protected function doUpgradeInstalledPackages($simulate)
    {
        list($rc) = $this->runBrew(array_merge(['upgrade', '--formula'], $simulate ? ['--dry-run'] : []), Command::OUTPUT_SHOW);
        if ($rc !== 0) {
            throw new Exception\SystemPackager('brew upgrade failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doExpandPackageList()
     */
    protected function doExpandPackageList(array $packages)
    {
        list($rc, $output) = $this->runBrew(array_merge(['deps', '--union', '--formula'], $packages), Command::OUTPUT_CAPTURE_STDOUT);
        if ($rc !== 0) {
            throw new Exception\SystemPackager("brew failed to list the dependencies:\n{$output}");
        }
        $installed = $this->listInstalledPackages();
        $toInstall = [];
        foreach (array_merge($packages, Regex::split('/\s+/', $output, -1, true)) as $name) {
            if (!in_array(self::getShortName($name), $installed, true) && !in_array($name, $toInstall, true)) {
                $toInstall[] = $name;
            }
        }
        if ($toInstall === []) {
            return [];
        }
        $result = $this->getFormulaVersions($toInstall);
        if (count($result) !== count($toInstall)) {
            throw new Exception\SystemPackager('brew failed to describe the formulae ' . implode(' ', $toInstall));
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
        list($rc, $output) = $this->runBrew(['list', '--formula', '-1'], Command::OUTPUT_CAPTURE_STDOUT);
        if ($rc !== 0) {
            throw new Exception\SystemPackager('brew list failed');
        }
        $result = [];
        foreach (Regex::split('/\s+/', $output, -1, true) as $name) {
            if ($this->validatePackageName($name, false)) {
                $result[] = $name;
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
        if ($this->cellar === null) {
            $this->cellar = '';
            $brew = $this->findBrew();
            if ($brew !== '') {
                list($rc, $output) = $this->os->getFilesystem()->run(self::createBrewCommand($brew, ['--cellar'], Command::OUTPUT_CAPTURE_STDOUT));
                if ($rc === 0) {
                    $this->cellar = rtrim(trim($output), '/');
                }
            }
        }
        if ($this->cellar === '') {
            return null;
        }
        // The files are symlinks to the installed version of the formula, like /opt/homebrew/Cellar/php/8.4.14_1/bin/php
        $match = null;
        if (!preg_match('#^' . preg_quote($this->cellar, '#') . '/(?<name>[^/]+)/(?<version>[^/]+)/#', $this->os->getFilesystem()->getRealPath($path), $match) || !$this->validatePackageName($match['name'], false)) {
            return null;
        }

        return new Package($match['name'], $match['version'], $match['name']);
    }

    /**
     * {@inheritdoc}
     *
     * Homebrew removes a formula only when asked to.
     *
     * @see \IPE\SystemPackager::doMarkAsManuallyInstalled()
     */
    protected function doMarkAsManuallyInstalled(array $packages)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doRemovePackages()
     */
    protected function doRemovePackages(array $packages)
    {
        list($rc) = $this->runBrew(array_merge(['uninstall', '--formula'], $packages), Command::OUTPUT_SHOW);
        if ($rc !== 0) {
            throw new Exception\SystemPackager('brew uninstall failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * brew uninstall doesn't remove the dependencies.
     *
     * @see \IPE\SystemPackager::doRestorePackages()
     */
    protected function doRestorePackages(array $packages)
    {
    }

    /**
     * {@inheritdoc}
     *
     * The cache of Homebrew is shared with the formulae installed by the user.
     *
     * @see \IPE\SystemPackager::doDeleteCache()
     */
    protected function doDeleteCache()
    {
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::validatePackageName()
     */
    protected function validatePackageName($name, $mayIncludeVersion)
    {
        // Like libzip, openssl@3, or shivammathur/php/php@8.4
        return preg_match('#^([a-z0-9][a-z0-9_\-]*/[a-z0-9][a-z0-9_\-]*/)?[a-z0-9][a-z0-9_.+@\-]*$#D', $name) === 1;
    }

    /**
     * @param non-empty-list<non-empty-string> $names
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return array<non-empty-string, non-empty-string> the keys are the names of the formulae, the values are their versions (with the revision, like 8.4.14_1)
     */
    private function getFormulaVersions(array $names)
    {
        $result = [];
        foreach ($this->describeFormulae($names) as $name => $formula) {
            if (!isset($formula['versions']) || !is_array($formula['versions'])) {
                continue;
            }
            $version = isset($formula['versions']['stable']) && is_string($formula['versions']['stable']) ? $formula['versions']['stable'] : '';
            if ($version === '') {
                continue;
            }
            $revision = isset($formula['revision']) && is_int($formula['revision']) ? $formula['revision'] : 0;
            $result[$name] = $revision > 0 ? "{$version}_{$revision}" : $version;
        }

        return $result;
    }

    /**
     * @param list<non-empty-string> $names
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return list<non-empty-string>
     */
    private function getKegOnlyFormulae(array $names)
    {
        if ($names === []) {
            return [];
        }
        $result = [];
        foreach ($this->describeFormulae($names) as $name => $formula) {
            if (isset($formula['keg_only']) && $formula['keg_only'] === true) {
                $result[] = $name;
            }
        }

        return $result;
    }

    /**
     * @param non-empty-list<non-empty-string> $names
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return array<non-empty-string, array<mixed>> the data of brew info, by formula name
     */
    private function describeFormulae(array $names)
    {
        list($rc, $output) = $this->runBrew(array_merge(['info', '--json=v2', '--formula'], $names), Command::OUTPUT_CAPTURE_STDOUT);
        if ($rc !== 0) {
            return [];
        }
        $data = json_decode($output, true);
        $result = [];
        if (!is_array($data) || !isset($data['formulae']) || !is_array($data['formulae'])) {
            return $result;
        }
        foreach ($data['formulae'] as $formula) {
            if (is_array($formula) && isset($formula['name']) && is_string($formula['name']) && $formula['name'] !== '') {
                $result[$formula['name']] = $formula;
            }
        }

        return $result;
    }

    /**
     * @param non-empty-string $name
     *
     * @return non-empty-string
     *
     * @example 'php@8.4' for 'shivammathur/php/php@8.4'
     */
    private static function getShortName($name)
    {
        $chunks = explode('/', $name);
        $result = array_pop($chunks);

        return $result === '' ? $name : $result;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if Homebrew is not installed
     */
    private function findBrew()
    {
        if ($this->brew === null) {
            $fs = $this->os->getFilesystem();
            $this->brew = $fs->resolveCommand('brew');
            // It's not in the PATH of non-login shells
            foreach (['/opt/homebrew/bin/brew', '/usr/local/bin/brew'] as $candidate) {
                if ($this->brew === '' && $fs->isFile($candidate)) {
                    $this->brew = $candidate;
                }
            }
        }

        return $this->brew;
    }

    /**
     * @param list<string> $arguments
     * @param non-empty-string $output one of the Command::OUTPUT_ constants
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager if Homebrew is not installed
     *
     * @return array{int, string} the exit code and the output
     */
    private function runBrew(array $arguments, $output)
    {
        $brew = $this->findBrew();
        if ($brew === '') {
            throw new Exception\SystemPackager('Homebrew is required to install the system packages: see https://brew.sh');
        }

        return $this->os->getFilesystem()->run(self::createBrewCommand($brew, $arguments, $output));
    }

    /**
     * @param non-empty-string $brew
     * @param list<string> $arguments
     * @param non-empty-string $output one of the Command::OUTPUT_ constants
     *
     * @return \IPE\Filesystem\Command
     */
    private static function createBrewCommand($brew, array $arguments, $output)
    {
        return Command::create($brew, $arguments)
            ->withOutput($output)
            // Never upgrade what's already installed (PHP included), and don't remove the older versions of the formulae
            ->withEnvironment([
                'HOMEBREW_NO_AUTO_UPDATE' => '1',
                'HOMEBREW_NO_INSTALL_UPGRADE' => '1',
                'HOMEBREW_NO_INSTALLED_DEPENDENTS_CHECK' => '1',
                'HOMEBREW_NO_INSTALL_CLEANUP' => '1',
                'HOMEBREW_NO_ENV_HINTS' => '1',
            ])
        ;
    }
}
