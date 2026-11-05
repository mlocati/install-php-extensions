<?php

namespace IPE\Extension\Version;

use Composer\Semver\VersionParser;
use IPE\Extension\Version;

/**
 * @see https://github.com/php/pie/blob/main/docs/extension-maintainers.md
 */
final class Composer extends Version
{
    /**
     * The dist archive of composer.
     *
     * @public
     * @var string
     */
    const DOWNLOADMETHOD_COMPOSER = 'composer-default';

    /**
     * An archive with the source code attached to a GitHub release.
     *
     * @public
     * @var string
     */
    const DOWNLOADMETHOD_PREPACKAGED_SOURCE = 'pre-packaged-source';

    /**
     * @var array<mixed>
     */
    private $composerJson;

    /**
     * @param non-empty-string $version
     * @param array<mixed> $composerJson the decoded metadata of the version, as published in the Composer repository
     */
    public function __construct($version, array $composerJson)
    {
        parent::__construct($version, VersionParser::parseStability($version));
        $this->composerJson = $composerJson;
    }

    /**
     * @return array<mixed>
     */
    public function getComposerJson()
    {
        return $this->composerJson;
    }

    /**
     * @return string empty string if not available
     */
    public function getPackageName()
    {
        return isset($this->composerJson['name']) && is_string($this->composerJson['name']) ? $this->composerJson['name'] : '';
    }

    /**
     * @return string empty string if not available
     */
    public function getDownloadURL()
    {
        $dist = $this->getDist();

        return isset($dist['url']) && is_string($dist['url']) ? $dist['url'] : '';
    }

    /**
     * @return string the type of the dist archive (zip, tar, ...), or an empty string if not available
     */
    public function getDownloadType()
    {
        $dist = $this->getDist();

        return isset($dist['type']) && is_string($dist['type']) ? $dist['type'] : '';
    }

    /**
     * @return non-empty-list<non-empty-string> the DOWNLOADMETHOD_... values (and the ones IPE doesn't support), in order of preference
     */
    public function getDownloadMethods()
    {
        $phpExt = $this->getPhpExt();
        $methods = isset($phpExt['download-url-method']) ? $phpExt['download-url-method'] : null;
        $result = [];
        foreach (is_string($methods) ? [$methods] : (is_array($methods) ? $methods : []) as $method) {
            if (is_string($method) && $method !== '') {
                $result[] = $method;
            }
        }

        return $result === [] ? [self::DOWNLOADMETHOD_COMPOSER] : $result;
    }

    /**
     * Get the name of the PHP extension.
     *
     * @return string empty string if not available
     */
    public function getExtensionName()
    {
        $phpExt = $this->getPhpExt();
        if (isset($phpExt['extension-name']) && is_string($phpExt['extension-name']) && $phpExt['extension-name'] !== '') {
            $name = $phpExt['extension-name'];
        } else {
            $packageName = explode('/', $this->getPackageName());
            $name = isset($packageName[1]) ? $packageName[1] : '';
        }

        return (string) preg_replace('/^ext-/', '', $name);
    }

    /**
     * Get the directory containing the source code of the extension, relative to the package root.
     *
     * @return string empty string if not specified
     */
    public function getBuildPath()
    {
        $phpExt = $this->getPhpExt();

        return isset($phpExt['build-path']) && is_string($phpExt['build-path']) ? $phpExt['build-path'] : '';
    }

    /**
     * @return string the GitHub repository (owner/name), or an empty string if the package is not hosted on GitHub
     */
    public function getGitHubRepository()
    {
        $match = null;
        if (preg_match('_^https://api\.github\.com/repos/([^/]+/[^/]+)/_', $this->getDownloadURL(), $match)) {
            return $match[1];
        }
        $source = isset($this->composerJson['source']) && is_array($this->composerJson['source']) ? $this->composerJson['source'] : [];
        $url = isset($source['url']) && is_string($source['url']) ? $source['url'] : '';
        if (preg_match('_^https://github\.com/([^/]+/[^/]+?)(?:\.git)?/?$_', $url, $match)) {
            return $match[1];
        }

        return '';
    }

    /**
     * Get the possible names of the source archives attached to the GitHub release.
     *
     * @return list<non-empty-string>
     */
    public function getPrePackagedSourceAssetNames()
    {
        $name = $this->getExtensionName();
        if ($name === '') {
            return [];
        }
        $version = $this->getVersion();

        return array_map('strtolower', [
            "php_{$name}-{$version}-src.tgz",
            "php_{$name}-{$version}-src.zip",
            // The format of the PECL tarballs
            "{$name}-{$version}.tgz",
        ]);
    }

    /**
     * Get the PHP versions supported by this version, as a composer version constraint.
     *
     * @return string empty string if not specified
     */
    public function getPHPRequirement()
    {
        $require = isset($this->composerJson['require']) && is_array($this->composerJson['require']) ? $this->composerJson['require'] : [];

        return isset($require['php']) && is_string($require['php']) ? trim($require['php']) : '';
    }

    /**
     * @return array<mixed>
     */
    private function getDist()
    {
        return isset($this->composerJson['dist']) && is_array($this->composerJson['dist']) ? $this->composerJson['dist'] : [];
    }

    /**
     * @return array<mixed>
     */
    private function getPhpExt()
    {
        return isset($this->composerJson['php-ext']) && is_array($this->composerJson['php-ext']) ? $this->composerJson['php-ext'] : [];
    }
}
