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
     * @see \IPE\SystemPackager::doInstallPackages()
     */
    protected function doInstallPackages(array $packages)
    {
        list($rc) = $this->os->getFilesystem()->run(Command::create('apk', array_merge(['add'], $this->forceOverwrite ? ['--force-overwrite'] : [], $packages))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('apk add failed');
        }
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
        $this->os->getFilesystem()->run(Command::raw('apk cache clean ; rm -rf /var/cache/apk/*')->withOutput(Command::OUTPUT_SHOW));
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
