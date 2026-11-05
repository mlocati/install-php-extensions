<?php

namespace IPE\SystemPackager;

use IPE\Exception;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\Service\Regex;
use IPE\SystemPackager;

class APT extends SystemPackager
{
    public function __construct(OS $os)
    {
        parent::__construct($os);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::getPhpizeRequirements()
     */
    public function getPhpizeRequirements()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::refresh()
     */
    public function refresh()
    {
        list($rc) = $this->os->getFilesystem()->run($this->createAptGetCommand(['update', '-qy'])->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('apt-get update failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doInstallPackages()
     */
    protected function doInstallPackages(array $packages)
    {
        list($rc) = $this->os->getFilesystem()->run($this->createAptGetCommand(array_merge(['install', '-qy', '--no-install-recommends'], $packages))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('apt-get install failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doUpgradeInstalledPackages()
     */
    protected function doUpgradeInstalledPackages($simulate)
    {
        list($rc) = $this->os->getFilesystem()->run($this->createAptGetCommand(array_merge(['upgrade', '-qy'], $simulate ? ['--simulate'] : []))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('apt-get upgrade failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doExpandPackageList()
     */
    protected function doExpandPackageList(array $packages)
    {
        list($rc, $output) = $this->os->getFilesystem()->run($this->createAptGetCommand(array_merge(['install', '-qy', '--simulate', '--no-install-recommends'], $packages)));
        if ($rc !== 0) {
            throw new Exception\SystemPackager("apt-get failed to list the packages:\n{$output}");
        }
        $result = [];
        $match = null;
        foreach (Regex::split('/[\r\n]/', $output, -1, true) as $line) {
            if (preg_match('#^\s*Inst\w*\s+(?<name>\S+)\s+\\((?<version>\S+)\s#D', $line, $match)) {
                $result[$match['name']] = $match['version'];
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
        list($rc, $output) = $this->os->getFilesystem()->run(Command::create('dpkg', ['--get-selections'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('dpkg --get-selections failed');
        }
        $result = [];
        $match = null;
        foreach (Regex::split('/[\r\n]+/', $output, -1, true) as $line) {
            // The architecture suffix (eg libc6:amd64) is not part of the package name
            if (preg_match('/^(?<name>[^\s:]+)(:\S+)?\s+install$/D', $line, $match)) {
                $result[] = $match['name'];
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
        $name = $this->getNameOfPackageOwningFile($path);
        $match = null;
        // With merged /usr, dpkg may still have registered the file in /bin, /sbin or /lib* (for example, /bin/dash in Debian 12)
        if ($name === '' && preg_match('#^/usr(?<path>/(?:s?bin|lib[^/]*)/.+)$#D', $path, $match)) {
            $name = $this->getNameOfPackageOwningFile($match['path']);
        }
        if ($name === '') {
            return null;
        }
        list($rc, $output) = $this->os->getFilesystem()->run(Command::create('dpkg-query', ['--show', '--showformat=${Package}|${Version}|${source:Package}\n', $name])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        if ($rc !== 0 || !preg_match('/^(?<name>[^|\s]+)\|(?<version>[^|\s]+)\|(?<source>[^|\s]+)$/m', $output, $match)) {
            return null;
        }

        return new InstalledPackage($match['name'], $match['version'], $match['source']);
    }

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if the file doesn't belong to any installed package
     */
    private function getNameOfPackageOwningFile($path)
    {
        list($rc, $output) = $this->os->getFilesystem()->run(Command::create('dpkg-query', ['--search', $path])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        if ($rc !== 0) {
            return '';
        }
        $match = null;
        foreach (Regex::split('/[\r\n]+/', $output, -1, true) as $line) {
            // Like "php8.4-cli: /usr/bin/php8.4" or "pkg1:amd64, pkg2:amd64: /path" (the diversion lines don't match)
            if (preg_match('#^(?<name>[a-z0-9][a-z0-9+\-.]+)(?::[a-z0-9\-]+)?(?:, [a-z0-9][a-z0-9+\-.]+(?::[a-z0-9\-]+)?)*: (?<path>/.*)$#D', $line, $match) && $match['path'] === $path) {
                return $match['name'];
            }
        }

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doMarkAsManuallyInstalled()
     */
    protected function doMarkAsManuallyInstalled(array $packages)
    {
        list($rc) = $this->os->getFilesystem()->run(Command::create('apt-mark', array_merge(['manual'], $packages))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('apt-mark manual failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doRemovePackages()
     */
    protected function doRemovePackages(array $packages)
    {
        list($rc) = $this->os->getFilesystem()->run($this->createAptGetCommand(array_merge(['remove', '--purge', '-qy'], $packages))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('apt-get remove failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doRestorePackages()
     */
    protected function doRestorePackages(array $packages)
    {
        list($rc) = $this->os->getFilesystem()->run($this->createAptGetCommand(array_merge(['install', '-qqy', '--no-install-recommends', '--no-upgrade'], $packages))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('apt-get install --no-upgrade failed');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doDeleteCache()
     */
    protected function doDeleteCache()
    {
        $this->os->getFilesystem()->run(Command::raw('apt-get clean -qy ; rm -rf /var/lib/apt/lists/*')->withOutput(Command::OUTPUT_SHOW));
    }

    /**
     * @param list<string> $arguments
     *
     * @return \IPE\Filesystem\Command
     */
    protected function createAptGetCommand(array $arguments)
    {
        return Command::create('apt-get', $arguments);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::validatePackageName()
     * @see https://www.debian.org/doc/debian-policy/ch-controlfields.html#s-f-source
     * @see https://www.debian.org/doc/debian-policy/ch-controlfields.html#s-f-version
     */
    protected function validatePackageName($name, $mayIncludeVersion)
    {
        // apt-get accepts regular expressions (eg ^libpng[0-9]+-[0-9]+$)
        if ($name[0] === '^') {
            return preg_match('#^\^[\w\\\\\-+.\[\]*?()|]+\$$#D', $name) === 1;
        }
        $rxName = '[a-z0-9][a-z0-9+\-.]+';
        $rx = '#^(' . $rxName . ')';
        if ($mayIncludeVersion) {
            $rxEpoch = '\d+:';
            $rxUpstreamVersion = '[A-Za-z0-9.+\-~]+';
            $rxDebianVersion = '[A-Za-z0-9+.~]+';
            $rxVersion = '(' . $rxEpoch . ')?(' . $rxUpstreamVersion . ')(-(' . $rxDebianVersion . '))?';
            $rx .= '((=)' . $rxVersion . ')?';
        }
        $rx .= '$#D';

        return preg_match($rx, $name) === 1;
    }
}
