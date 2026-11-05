<?php

namespace IPE\SystemPackager\APT;

use IPE\Exception;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\Service\Regex;
use IPE\Service\Url;
use IPE\SystemPackager\APT;

/**
 * @property \IPE\OS\Debian $os
 */
final class OnDebian extends APT
{
    /**
     * @var non-empty-string
     * @public
     */
    const ARCHIVEURL_DEFAULT = 'http://archive.debian.org/debian';

    /**
     * @var string
     * @public
     */
    const ARCHIVEURL_SECURITY = 'http://archive.debian.org/debian-security';

    /**
     * @var string
     * @public
     */
    const ARCHIVEURL_UPDATES = 'http://archive.debian.org/debian';

    /**
     * bullseye-security is not in the Debian archive yet, and its packages are gone from security.debian.org: the last snapshot that has them.
     *
     * @public
     * @var string
     */
    const ARCHIVEURL_SECURITY_BULLSEYE = 'http://snapshot.debian.org/archive/debian-security/20260903T220410Z';

    /**
     * @var bool
     */
    private $fixArchived;

    /**
     * @var array<string, string> keys: default, security, updates
     */
    private $archiveUrls;

    /**
     * @var bool
     */
    private $isFirstRefreshCall = true;

    /**
     * @param \IPE\OS\Debian $os
     * @param array{fixArchived?: bool, archiveUrls?: array<string, string>} $config
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    public function __construct(OS\Debian $os, array $config = [])
    {
        parent::__construct($os);
        $this->fixArchived = isset($config['fixArchived']) ? $config['fixArchived'] : false;
        $this->archiveUrls = [
            'default' => self::ARCHIVEURL_DEFAULT,
            'security' => self::ARCHIVEURL_SECURITY,
            'updates' => self::ARCHIVEURL_UPDATES,
        ];
        if (isset($config['archiveUrls'])) {
            foreach ($config['archiveUrls'] as $which => $url) {
                $this->setArchiveUrl($which, $url);
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::refresh()
     */
    public function refresh()
    {
        if ($this->isFirstRefreshCall && $this->fixArchived) {
            $this->isFirstRefreshCall = false;
            $aptUpdateInvoked = $this->firstRefresh();
            if ($aptUpdateInvoked) {
                return;
            }
        }
        parent::refresh();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager\APT::createAptGetCommand()
     * @see https://bugs.launchpad.net/ubuntu/+source/apt/+bug/1332440
     */
    protected function createAptGetCommand(array $arguments)
    {
        if ($this->os->getNumericVersion() !== OS\Debian::VERSION_JESSIE) {
            return parent::createAptGetCommand($arguments);
        }
        // apt-get is very slow when the limit is very high: a shell is required since PHP can't change the limit before 7.0
        $commandLine = 'if [ "$(ulimit -n)" -gt 10000 2>/dev/null ]; then ulimit -n 10000; fi; exec apt-get';
        foreach ($arguments as $argument) {
            $commandLine .= ' ' . $this->os->getFilesystem()->escapeShellArg($argument);
        }

        return Command::raw($commandLine);
    }

    /**
     * @return bool
     */
    public function isFixArchived()
    {
        return $this->fixArchived;
    }

    /**
     * @param string $which one of the keys of $this->archiveUrls (it comes from the user configuration)
     * @param string $url
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return void
     */
    private function setArchiveUrl($which, $url)
    {
        if (!isset($this->archiveUrls[$which])) {
            throw new Exception\InvalidArgument('Invalid argument $config.archiveUrls');
        }
        $required = $which === 'default';
        if ($url === '') {
            if ($required) {
                throw new Exception\InvalidArgument("Invalid argument \$config.archiveUrls[{$which}]: the URL must be specified");
            }
        } else {
            $url = Url::validate($url, "\$config.archiveUrls[{$which}]");
        }
        $this->archiveUrls[$which] = $url;
    }

    /**
     * @param 'default'|'security'|'updates' $which
     *
     * @return string
     */
    public function getArchiveUrl($which)
    {
        return $this->archiveUrls[$which];
    }

    /**
     * @return array<string, string> keys: default, security, updates
     */
    public function getArchiveUrls()
    {
        return $this->archiveUrls;
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return bool true if apt-get update is called (succesfully)
     */
    private function firstRefresh()
    {
        $version = $this->os->getNumericVersion();
        if ($version > OS\Debian::VERSION_BULLSEYE) {
            return false;
        }
        // Only bullseye-updates is available in the Debian archive
        $hasUpdates = $version === OS\Debian::VERSION_BULLSEYE;
        $securityUrl = $this->archiveUrls['security'];
        if ($version === OS\Debian::VERSION_BULLSEYE && $securityUrl === self::ARCHIVEURL_SECURITY) {
            $securityUrl = self::ARCHIVEURL_SECURITY_BULLSEYE;
        }
        $fs = $this->os->getFilesystem();
        $process = [
            '/etc/apt/sources.list' => $this->os->getCodename(),
        ];
        if ($this->os->getNumericVersion() === OS\Debian::VERSION_STRETCH && $fs->exists('/etc/apt/sources.list.d/buster.list')) {
            $process['/etc/apt/sources.list.d/buster.list'] = 'buster';
        }
        foreach ($process as $file => $codename) {
            $originalConfig = $fs->readFile($file);
            $newConfig = Regex::replaceCallback(
                '/^(?<before>[ \t]*deb\b.*[ \t])(?<url>https?:\/\/[^\s]+)(?<after>[ \t]+' . $codename . '(?<codenameSuffix>(-[\w][\w\-]*)?)[^\r\n]*)/m',
                function (array $matches) use ($hasUpdates, $securityUrl) {
                    return $this->fixArchivedDeb($matches, $hasUpdates, $securityUrl);
                },
                $originalConfig
            );
            if ($newConfig === $originalConfig) {
                continue;
            }
            $quotedFile = $fs->escapeShellArg($file);
            $fs->run(Command::raw("printf '\\nThe file %s has been fixed with the Debian archives\\nORIGINAL CONTENT:\\n' {$quotedFile} && grep -vE '^[ \\t]*#' {$quotedFile}")->withOutput(Command::OUTPUT_SHOW));
            $fs->writeFile($file, $newConfig);
            $fs->run(Command::raw("echo '\\nNEW CONTENT:' && grep -vE '^[ \\t]*#' {$quotedFile} && printf '\\n\\n'")->withOutput(Command::OUTPUT_SHOW));
        }
        if ($version === OS\Debian::VERSION_BULLSEYE) {
            // The Release file of the security snapshot is expired
            $fs->writeFile('/etc/apt/apt.conf.d/99-ipe-no-check-valid-until', "Acquire::Check-Valid-Until \"false\";\n");
        }
        $fs->run(Command::raw("echo 'Performing first apt-get update. Please wait...'")->withOutput(Command::OUTPUT_SHOW));
        $this->doDeleteCache();
        list($rc, $output) = $fs->run($this->createAptGetCommand(['update', '-qy']));
        $fs->run(Command::raw("echo 'done.'")->withOutput(Command::OUTPUT_SHOW));
        $isKeyExpired = preg_match('#\b(KEYEXPIRED|EXPKEYSIG)\s+[0-9A-Z]#', $output) ? true : false;
        if ($isKeyExpired) {
            // See: man 5 apt.conf
            // See: man 8 apt-get ("Get" subsection)
            $fs->writeFile('/etc/apt/apt.conf.d/99-ipe-unauthenticated', "APT::Get::AllowUnauthenticated \"true\";\nAcquire::Check-Valid-Until \"false\";\n");
            $fs->run(Command::raw(
                "printf '############\\n# WARNING! #\\n############\\napt packages will be installed without checking authenticity!\\n############\\nWe created the file /etc/apt/apt.conf.d/99-ipe-unauthenticated with this content:\\n' && cat /etc/apt/apt.conf.d/99-ipe-unauthenticated && printf '\\n'"
            )->withOutput(Command::OUTPUT_SHOW));
        }
        if ($rc === 0 && !$isKeyExpired) {
            return true;
        }
        $this->doDeleteCache();

        return false;
    }

    /**
     * Rewrite a "deb" line pointing to a repository that is no longer online.
     *
     * @param array<array-key, string> $matches
     * @param bool $hasUpdates whether the -updates repository is available in the archive
     * @param string $securityUrl empty string to disable the security repository
     *
     * @return string
     */
    private function fixArchivedDeb(array $matches, $hasUpdates, $securityUrl)
    {
        $url = rtrim($matches['url'], '/');
        $suffix = $matches['codenameSuffix'];
        $isMain = in_array($url, ['http://deb.debian.org/debian', 'http://httpredir.debian.org/debian'], true);
        if ($suffix === '' && $isMain) {
            // deb http://deb.debian.org/debian jessie main
            return "{$matches['before']}{$this->archiveUrls['default']}{$matches['after']}";
        }
        $isSecurity = ($suffix === '' && in_array($url, ['http://security.debian.org/debian-security', 'http://security.debian.org', 'http://deb.debian.org/debian-security'], true))
            || ($suffix === '-security' && in_array($url, ['http://deb.debian.org/debian-security', 'http://httpredir.debian.org/debian-security', 'http://security.debian.org/debian-security'], true));
        if ($isSecurity) {
            // deb http://security.debian.org/debian-security jessie/updates main
            // deb http://deb.debian.org/debian-security bullseye-security main
            return $securityUrl === '' ? '#' . $matches[0] : "{$matches['before']}{$securityUrl}{$matches['after']}";
        }
        if ($suffix === '-updates' && $isMain) {
            // deb http://deb.debian.org/debian jessie-updates main
            return $this->archiveUrls['updates'] === '' || !$hasUpdates ? '#' . $matches[0] : "{$matches['before']}{$this->archiveUrls['updates']}{$matches['after']}";
        }

        return $matches[0];
    }
}
