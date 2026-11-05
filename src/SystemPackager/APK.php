<?php

namespace IPE\SystemPackager;

use IPE\Exception;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\Service\Regex;
use IPE\SystemPackager;

final class APK extends SystemPackager
{
    /**
     * @var string
     */
    const PACKAGE_ICU_DATA_EN = 'icu-data-en';

    /**
     * Overwrite files in other packages?
     *
     * @var bool
     */
    private $forceOverwrite = false;

    public function __construct(OS $os)
    {
        parent::__construct($os);
    }

    /**
     * Overwrite files in other packages?
     *
     * @return bool
     */
    public function isForceOverwrite()
    {
        return $this->forceOverwrite;
    }

    /**
     * Overwrite files in other packages?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setForceOverwrite($value)
    {
        $this->forceOverwrite = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::refresh()
     */
    public function refresh()
    {
        list($rc) = $this->os->getFilesystem()->run(Command::create('apk', ['update'])->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('apk update failed');
        }
    }

    /**
     * Like the $PHPIZE_DEPS env var of Alpine docker images.
     *
     * @see https://github.com/docker-library/php/blob/3099068733b51ad67ce19b49fc32d949cb432181/5.6/alpine/Dockerfile#L4-L12
     * @see https://github.com/docker-library/php/blob/80245dacccf5ba76d4daed9b3dfc86a57652ca3f/8.4/alpine3.22/cli/Dockerfile#L11-L20
     *
     * {@inheritdoc}
     * @see \IPE\SystemPackager::getPhpizeRequirements()
     */
    public function getPhpizeRequirements()
    {
        return [
            'autoconf',
            'dpkg-dev',
            'dpkg',
            'file',
            'g++',
            'gcc',
            'libc-dev',
            'make',
            'pkgconf',
            're2c',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::formatPackageNameWithVersion()
     */
    public function formatPackageNameWithVersion($name, $version)
    {
        return "{$name}={$version}";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getPHPDevelopmentPackageName()
     */
    public function getPHPDevelopmentPackageName(Package $phpPackage)
    {
        return "{$phpPackage->getSource()}-dev";
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
        // Most of the PECL packages don't provide the name without "pecl-" (for example, php84-pecl-imagick)
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
     * @see \IPE\SystemPackager::doInstallPackages()
     */
    protected function doInstallPackages(array $packages)
    {
        $fs = $this->os->getFilesystem();
        list($rc) = $fs->run(Command::create('apk', array_merge(['add'], $this->forceOverwrite ? ['--force-overwrite'] : [], $packages))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('apk add failed');
        }
        $pinned = [];
        foreach ($packages as $package) {
            $name = (string) preg_replace('/[<>=~].*$/', '', $package);
            if ($name !== $package && $name !== '') {
                $pinned[] = $name;
            }
        }
        if ($pinned !== []) {
            // apk keeps the version constraints in the "world" file, and they would block the upgrades
            list($rc) = $fs->run(Command::create('apk', array_merge(['add'], $pinned))->withOutput(Command::OUTPUT_SHOW));
            if ($rc !== 0) {
                throw new Exception\SystemPackager('apk add failed');
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doFindAvailablePackage()
     */
    protected function doFindAvailablePackage($name, $version)
    {
        $fs = $this->os->getFilesystem();
        // It also finds the packages providing $name (for example, php83-pecl-redis provides php83-redis)
        list($rc, $output) = $fs->run(Command::create('apk', array_merge(['search', '--exact'], $version === '' ? [] : ['--all'], [$name]))->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        if ($rc !== 0) {
            return null;
        }
        $found = null;
        $match = null;
        foreach (Regex::split('/[\r\n]+/', $output, -1, true) as $line) {
            // Like "php83-pecl-redis-6.3.0-r0"
            if (preg_match('/^(?<name>\S+?)-(?<version>\d[^\s\-]*-r\d+)$/D', trim($line), $match) && ($version === '' || $match['version'] === $version)) {
                $found = $match;
                break;
            }
        }
        if ($found === null) {
            return null;
        }
        list($rc, $output) = $fs->run(Command::create('apk', ['list', '--available', $found['name']])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        // Like "php83-pdo_mysql-8.3.36-r0 x86_64 {php83} (PHP-3.01) [installed]"
        if ($rc !== 0 || !preg_match('/^' . preg_quote("{$found['name']}-{$found['version']}", '/') . ' \S+ \{(?<origin>[^}\s]+)\}/m', $output, $match)) {
            return null;
        }

        return new Package($found['name'], $found['version'], $match['origin']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getUpstreamVersion()
     */
    public function getUpstreamVersion($version)
    {
        $match = null;
        // Without the suffixes like _rc1 or _p2, and the release of the package (-r0)
        if (!preg_match('/^(?<upstream>\d+(?:\.\d+)*[a-z]?)/', $version, $match)) {
            return '';
        }

        return $match['upstream'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doUpgradeInstalledPackages()
     */
    protected function doUpgradeInstalledPackages($simulate)
    {
        list($rc) = $this->os->getFilesystem()->run(Command::create('apk', array_merge(['upgrade'], $simulate ? ['--simulate'] : []))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('apk upgrade failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doExpandPackageList()
     */
    protected function doExpandPackageList(array $packages)
    {
        list($rc, $output) = $this->os->getFilesystem()->run(Command::create('apk', array_merge(['add', '--simulate'], $packages)));
        if ($rc !== 0) {
            throw new Exception\SystemPackager("apk failed to list the packages:\n{$output}");
        }
        $result = [];
        $match = null;
        foreach (Regex::split('/[\r\n]/', $output, -1, true) as $line) {
            if (preg_match('#^\s*\(\s*\d+/\d+\)\s+Installing\s+(?<name>\S+)\s+\((?<version>[^)]+)\)#D', $line, $match)) {
                $result[$match['name']] = $match['version'];
            }
        }
        // apk picks it to satisfy icu-libs: once in the "world" file, icu-data-full could not be installed later
        if (!in_array(self::PACKAGE_ICU_DATA_EN, $packages, true)) {
            unset($result[self::PACKAGE_ICU_DATA_EN]);
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
        list($rc, $output) = $this->os->getFilesystem()->run(Command::create('apk', ['info'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('apk info failed');
        }
        $result = [];
        foreach (Regex::split('/[\r\n]+/', $output, -1, true) as $line) {
            $line = trim($line);
            if ($line !== '') {
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
        $fs = $this->os->getFilesystem();
        list($rc, $output) = $fs->run(Command::create('apk', ['info', '--quiet', '--who-owns', $path])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        $name = trim($output);
        if ($rc !== 0 || $name === '' || !$this->validatePackageName($name, false)) {
            return null;
        }
        list($rc, $output) = $fs->run(Command::create('apk', ['list', '--installed', $name])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        $match = null;
        // Like "php84-8.4.27-r0 x86_64 {php84} (PHP-3.01 BSD-3-Clause) [installed]"
        if ($rc !== 0 || !preg_match('/^' . preg_quote($name, '/') . '-(?<version>\d\S*) \S+ \{(?<origin>[^}\s]+)\}/m', $output, $match)) {
            return null;
        }

        return new Package($name, $match['version'], $match['origin']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doMarkAsManuallyInstalled()
     */
    protected function doMarkAsManuallyInstalled(array $packages)
    {
        // Adding an installed package adds it to the "world" file
        list($rc) = $this->os->getFilesystem()->run(Command::create('apk', array_merge(['add'], $packages))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('apk add failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doRemovePackages()
     */
    protected function doRemovePackages(array $packages)
    {
        list($rc) = $this->os->getFilesystem()->run(Command::create('apk', array_merge(['del', '--purge'], $packages))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('apk del failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doRestorePackages()
     */
    protected function doRestorePackages(array $packages)
    {
        // apk del never removes the packages listed in the "world" file, nor their dependencies
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doDeleteCache()
     */
    protected function doDeleteCache()
    {
        // Old versions of apk fail with "Package cache is not enabled" when /etc/apk/cache doesn't exist
        $this->os->getFilesystem()->run(Command::raw('if [ -e /etc/apk/cache ]; then apk cache clean; fi; rm -rf /var/cache/apk/*')->withOutput(Command::OUTPUT_SHOW));
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::validatePackageName()
     * @see https://wiki.alpinelinux.org/wiki/Package_policies
     * @see https://pkgs.alpinelinux.org/packages
     * @see https://dl-cdn.alpinelinux.org/alpine/edge/main/x86_64/APKINDEX.tar.gz
     */
    protected function validatePackageName($name, $mayIncludeVersion)
    {
        // The name of virtual packages starts with a dot (eg .persistent-deps in old docker images)
        $rxName = '\.?[0-9]*[a-z][a-z0-9\+]*([._\-][a-z0-9+]+)*';
        $rx = '#^(' . $rxName . ')';
        if ($mayIncludeVersion) {
            $rxVersion = '[0-9]+[a-z]?(\.[0-9]+[a-z]?|[\-_][a-z0-9]+)*?';
            $rx .= '((<|<=|=|>=|>|=~)(' . $rxVersion . '))?';
        }
        $rx .= '$#D';

        return preg_match($rx, $name) === 1;
    }
}
