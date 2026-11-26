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
     * The tap with the formulae of the PHP extensions, used only when the user tapped and trusted it (together with PHP_TAP).
     *
     * @var non-empty-string
     */
    const EXTENSIONS_TAP = 'shivammathur/extensions';

    /**
     * The tap with the PHP formulae the formulae of EXTENSIONS_TAP depend on.
     *
     * @var non-empty-string
     */
    const PHP_TAP = 'shivammathur/php';

    /**
     * @var string|null
     */
    private $brew = null;

    /**
     * @var list<string>|null the full names of the formulae of EXTENSIONS_TAP (empty if it or PHP_TAP are not tapped and trusted)
     */
    private $extensionsTapFormulae = null;

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
        // The .pc files may require the ones of keg-only dependencies (like vips with libarchive): only pkg-config needs them
        foreach ($this->getKegOnlyFormulae(array_values(array_diff($this->listDependencies($packages), $packages))) as $name) {
            $pkgConfigPaths[] = $this->getFormulaPrefix($name) . '/lib/pkgconfig';
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
     * {@inheritdoc}
     *
     * The formulae of EXTENSIONS_TAP install the headers in <prefix>/include/php/ext/<extension>@<PHP version>, but the extensions include them from <PHP include dir>/ext/<extension>.
     *
     * @see \IPE\SystemPackager::exposeExtensionHeaders()
     */
    public function exposeExtensionHeaders($packageName, $phpIncludeDir)
    {
        $result = [];
        if (strpos($packageName, self::EXTENSIONS_TAP . '/') !== 0) {
            return $result;
        }
        $prefix = $this->getFormulaPrefix($packageName);
        if ($prefix === '') {
            return $result;
        }
        $fs = $this->os->getFilesystem();
        $headersDir = "{$prefix}/include/php/ext";
        if (!$fs->isDirectory($headersDir)) {
            return $result;
        }
        foreach ($fs->listDirectoryContents($headersDir) as $name) {
            $matches = null;
            if (!preg_match('/^(.+)@\d+\.\d+$/', $name, $matches)) {
                continue;
            }
            $link = "{$phpIncludeDir}/ext/{$matches[1]}";
            // Like the headers installed by a compiled extension
            if ($fs->exists($link) || $fs->isSymlink($link)) {
                continue;
            }
            $fs->createSymlink("{$headersDir}/{$name}", $link);
            $result[] = $link;
        }

        return $result;
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
     * homebrew-core doesn't have formulae for single PHP extensions: only EXTENSIONS_TAP has them.
     *
     * @see \IPE\SystemPackager::getPHPExtensionPackageCandidates()
     */
    public function getPHPExtensionPackageCandidates(Package $phpPackage, $extensionName)
    {
        return $this->getExtensionFormulae($phpPackage, [strtolower($extensionName)]);
    }

    /**
     * Get the formulae of EXTENSIONS_TAP for the installed PHP, if the user tapped and trusted it and PHP_TAP.
     *
     * @param list<non-empty-string> $names the names of the formulae without the PHP version, in order of preference
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return list<non-empty-string> only the formulae that exist
     *
     * @example ['shivammathur/extensions/phpredis@8.4'] for ['phpredis'] with php@8.4
     */
    public function getExtensionFormulae(Package $phpPackage, array $names)
    {
        $phpVersion = self::getPHPFormulaVersion($phpPackage);
        if ($phpVersion === '') {
            return [];
        }
        $result = [];
        foreach ($names as $name) {
            $candidate = self::EXTENSIONS_TAP . "/{$name}@{$phpVersion}";
            if (in_array($candidate, $this->listExtensionsTapFormulae(), true)) {
                $result[] = $candidate;
            }
        }

        return $result;
    }

    /**
     * Get a formula of EXTENSIONS_TAP that doesn't depend on the PHP version (like a library), if the user tapped and trusted it and PHP_TAP.
     *
     * @param non-empty-string $name
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return string empty string if it's not available
     *
     * @example 'shivammathur/extensions/imap-uw' for 'imap-uw'
     */
    public function getExtensionsTapFormula($name)
    {
        $candidate = self::EXTENSIONS_TAP . "/{$name}";

        return in_array($candidate, $this->listExtensionsTapFormulae(), true) ? $candidate : '';
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
        // brew info fails if a formula doesn't exist, and the aliases (like openssl for openssl@4) are described with the names of their formulae
        $result = $this->getFormulaVersions($toInstall);
        if ($result === []) {
            throw new Exception\SystemPackager('brew failed to describe the formulae ' . implode(' ', $toInstall));
        }
        foreach (array_keys($result) as $name) {
            if (in_array(self::getShortName($name), $installed, true)) {
                unset($result[$name]);
            }
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
     * After a failed installation some formulae may be missing, and brew uninstall would fail.
     *
     * @see \IPE\SystemPackager::doRemovePackages()
     */
    protected function doRemovePackages(array $packages)
    {
        $installed = $this->listInstalledPackages();
        $toRemove = [];
        foreach ($packages as $package) {
            if (in_array(self::getShortName($package), $installed, true)) {
                $toRemove[] = $package;
            }
        }
        if ($toRemove === []) {
            return;
        }
        list($rc) = $this->runBrew(array_merge(['uninstall', '--formula'], $toRemove), Command::OUTPUT_SHOW);
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
     * @return array<non-empty-string, non-empty-string> the keys are the full names of the formulae, the values are their versions (with the revision, like 8.4.14_1)
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
     * @return list<non-empty-string> the runtime dependencies of the formulae, recursively
     */
    private function listDependencies(array $names)
    {
        if ($names === []) {
            return [];
        }
        // Without --union, brew lists only the dependencies shared by all the formulae
        list($rc, $output) = $this->runBrew(array_merge(['deps', '--formula', '--union', '--full-name'], $names), Command::OUTPUT_CAPTURE_STDOUT);
        if ($rc !== 0) {
            return [];
        }

        return preg_split('/\s+/', $output, -1, PREG_SPLIT_NO_EMPTY) ?: [];
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
     * @return array<non-empty-string, array<mixed>> the data of brew info, by full formula name
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
            if (!is_array($formula)) {
                continue;
            }
            // The full name (like shivammathur/extensions/xdebug@8.4) of the formulae of the taps isn't ambiguous
            if (isset($formula['full_name']) && is_string($formula['full_name']) && $formula['full_name'] !== '') {
                $result[$formula['full_name']] = $formula;
            } elseif (isset($formula['name']) && is_string($formula['name']) && $formula['name'] !== '') {
                $result[$formula['name']] = $formula;
            }
        }

        return $result;
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return list<string>
     */
    private function listExtensionsTapFormulae()
    {
        if ($this->extensionsTapFormulae === null) {
            $this->extensionsTapFormulae = [];
            list($rc, $output) = $this->runBrew(['tap-info', '--json', self::EXTENSIONS_TAP, self::PHP_TAP], Command::OUTPUT_CAPTURE_STDOUT);
            $data = $rc === 0 ? json_decode($output, true) : null;
            $taps = [];
            foreach (is_array($data) ? $data : [] as $tap) {
                if (self::isTapUsable($tap)) {
                    $taps[$tap['name']] = $tap['formula_names'];
                }
            }
            // Even when installing the bottles, brew resolves the dependency of the formulae of the extensions on the PHP formulae of PHP_TAP
            if (isset($taps[self::EXTENSIONS_TAP], $taps[self::PHP_TAP])) {
                foreach ($taps[self::EXTENSIONS_TAP] as $name) {
                    if (is_string($name)) {
                        $this->extensionsTapFormulae[] = $name;
                    }
                }
            }
        }

        return $this->extensionsTapFormulae;
    }

    /**
     * Homebrew 7 refuses to load the formulae of the taps that the user didn't trust (older versions don't have "trusted").
     *
     * @param mixed $tap an item of the output of brew tap-info --json
     *
     * @return bool
     *
     * @phpstan-assert-if-true array{name: string, formula_names: array<mixed>} $tap
     */
    private static function isTapUsable($tap)
    {
        return is_array($tap)
            && isset($tap['name'], $tap['installed'], $tap['formula_names'])
            && is_string($tap['name'])
            && $tap['installed'] === true
            && is_array($tap['formula_names'])
            && (!isset($tap['trusted']) || $tap['trusted'] === true);
    }

    /**
     * @return string empty string if EXTENSIONS_TAP doesn't have the extensions of the formula (like php@8.4-zts)
     *
     * @example '8.4' for php@8.4
     * @example '8.5' for php 8.5.0_1
     */
    private static function getPHPFormulaVersion(Package $phpPackage)
    {
        $match = null;
        if (preg_match('/^php@(?<version>\d+\.\d+)$/D', $phpPackage->getName(), $match)) {
            return $match['version'];
        }
        if ($phpPackage->getName() === 'php' && preg_match('/^(?<version>\d+\.\d+)\./', $phpPackage->getVersion(), $match)) {
            return $match['version'];
        }

        return '';
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
