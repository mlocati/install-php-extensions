<?php

namespace IPE;

use IPE\Extension\Version;
use IPE\Filesystem\Downloader;
use IPE\Service\JSON;

/**
 * A repository that uses the version 2 of the Composer repository protocol.
 *
 * @see https://getcomposer.org/doc/05-repositories.md#composer
 */
final class ComposerRepository
{
    /**
     * @public
     * @var int
     */
    const DEFAULT_PRIORITY = 0;

    /**
     * @var non-empty-string
     */
    private $url;

    /**
     * @var int
     */
    private $priority;

    /**
     * The parts of metadata-url before and after %package% (null until packages.json has been read).
     *
     * @var array{string, string}|null
     */
    private $metadataURL;

    /**
     * The regular expressions matching the names of the available packages (null if the repository doesn't provide them).
     *
     * @var list<non-empty-string>|null
     */
    private $availablePackages;

    /**
     * @param non-empty-string $url the URL of the repository (or of its packages.json file)
     * @param int $priority the repositories with a higher priority are searched first
     * @param string $metadataURL the metadata-url of the repository, to avoid reading its packages.json file
     *
     * @throws \IPE\Exception\InvalidArgument if $metadataURL doesn't contain %package%
     */
    public function __construct($url, $priority = self::DEFAULT_PRIORITY, $metadataURL = '')
    {
        $this->url = self::normalizeURL($url);
        $this->priority = $priority;
        if ($metadataURL !== '') {
            $this->metadataURL = self::splitMetadataURL($metadataURL);
            if ($this->metadataURL === null) {
                throw new Exception\InvalidArgument("The metadata URL {$metadataURL} doesn't contain %package%");
            }
        }
    }

    /**
     * @param non-empty-string $url
     *
     * @return non-empty-string
     */
    public static function normalizeURL($url)
    {
        return rtrim($url, '/') ?: $url;
    }

    /**
     * @return non-empty-string
     */
    public function getURL()
    {
        return $this->url;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param non-empty-string $packageName
     * @param bool $includeDev
     *
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Extension\Version\Composer[]|null null if the repository doesn't provide the package
     */
    public function getVersions(Downloader $downloader, $packageName, $includeDev)
    {
        $packageName = strtolower($packageName);
        $metadataURL = $this->loadPackagesJson($downloader);
        if ($this->availablePackages !== null && !$this->isPackageAvailable($packageName)) {
            return null;
        }
        $versions = $this->fetchVersions($downloader, $metadataURL, $packageName, false);
        $devVersions = $includeDev ? $this->fetchVersions($downloader, $metadataURL, $packageName, true) : null;
        if ($versions === null && $devVersions === null) {
            return null;
        }

        return array_merge($versions ?: [], $devVersions ?: []);
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return array{string, string} the parts of metadata-url before and after %package%
     */
    private function loadPackagesJson(Downloader $downloader)
    {
        if ($this->metadataURL !== null) {
            return $this->metadataURL;
        }
        $path = (string) parse_url($this->url, PHP_URL_PATH);
        $data = $this->decodeJson($downloader->getContent(strpos($path, '.json') === false ? "{$this->url}/packages.json" : $this->url));
        $metadataURL = isset($data['metadata-url']) && is_string($data['metadata-url']) ? $data['metadata-url'] : '';
        if ($metadataURL === '') {
            throw new Exception\IO("The Composer repository {$this->url} doesn't support the version 2 of the protocol (missing metadata-url)");
        }
        $match = null;
        if ($metadataURL[0] === '/' && preg_match('_^[^:]+://[^/]*_', $this->url, $match)) {
            $metadataURL = $match[0] . $metadataURL;
        }
        $metadataURLParts = self::splitMetadataURL($metadataURL);
        if ($metadataURLParts === null) {
            throw new Exception\IO("The metadata-url of the Composer repository {$this->url} doesn't contain %package%");
        }
        $patterns = [];
        foreach (isset($data['available-packages']) && is_array($data['available-packages']) ? $data['available-packages'] : [] as $name) {
            if (is_string($name) && $name !== '') {
                $patterns[] = '{^' . preg_quote($name) . '$}i';
            }
        }
        foreach (isset($data['available-package-patterns']) && is_array($data['available-package-patterns']) ? $data['available-package-patterns'] : [] as $pattern) {
            if (is_string($pattern) && $pattern !== '') {
                $patterns[] = '{^' . str_replace('\\*', '.*', preg_quote($pattern)) . '$}i';
            }
        }
        $this->metadataURL = $metadataURLParts;
        $this->availablePackages = $patterns === [] ? null : $patterns;

        return $this->metadataURL;
    }

    /**
     * @param string $metadataURL
     *
     * @return array{string, string}|null null if $metadataURL doesn't contain %package%
     */
    private static function splitMetadataURL($metadataURL)
    {
        $parts = explode('%package%', $metadataURL, 2);

        return count($parts) === 2 ? [$parts[0], $parts[1]] : null;
    }

    /**
     * @param non-empty-string $packageName
     *
     * @return bool
     */
    private function isPackageAvailable($packageName)
    {
        foreach ((array) $this->availablePackages as $pattern) {
            if (preg_match($pattern, $packageName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{string, string} $metadataURL
     * @param non-empty-string $packageName
     * @param bool $dev
     *
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Extension\Version\Composer[]|null null if the repository doesn't provide the package
     */
    private function fetchVersions(Downloader $downloader, array $metadataURL, $packageName, $dev)
    {
        $url = $metadataURL[0] . $packageName . ($dev ? '~dev' : '') . $metadataURL[1];
        try {
            $json = $downloader->getContent($url);
        } catch (Exception\IO\NotFound $_) {
            return null;
        }
        $data = $this->decodeJson($json);
        $versions = null;
        if (isset($data['packages']) && is_array($data['packages'])) {
            foreach ($data['packages'] as $name => $packageVersions) {
                if (strcasecmp((string) $name, $packageName) === 0) {
                    $versions = $packageVersions;
                    break;
                }
            }
        }
        if (!is_array($versions)) {
            throw new Exception\IO("Missing the versions of {$packageName} in {$url}");
        }
        $versions = array_values($versions);
        if (isset($data['minified']) && $data['minified'] === 'composer/2.0') {
            $versions = self::expand($versions);
        }

        return array_values(
            array_filter(
                array_map(
                    static function ($version) {
                        return is_array($version) ? self::parseVersion($version) : null;
                    },
                    $versions
                )
            )
        );
    }

    /**
     * @param string $json
     *
     * @throws \IPE\Exception\IO
     *
     * @return array<mixed>
     */
    private function decodeJson($json)
    {
        try {
            $data = JSON::decode($json, true);
        } catch (Exception\JSON $x) {
            throw new Exception\IO("Failed to decode JSON from the Composer repository {$this->url}:\n{$x->getMessage()}");
        }
        if (!is_array($data)) {
            throw new Exception\IO("Failed to decode JSON from the Composer repository {$this->url}");
        }

        return $data;
    }

    /**
     * @param array<mixed> $version the decoded metadata of a version, as published in the repository
     *
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Extension\Version\Composer|null null if the version is not a PHP extension
     */
    private static function parseVersion(array $version)
    {
        if (!isset($version['type']) || !in_array($version['type'], ['php-ext', 'php-ext-zend'], true)) {
            return null;
        }
        $versionString = isset($version['version']) && is_string($version['version']) ? $version['version'] : '';
        if ($versionString === '') {
            throw new Exception\IO('Missing version in the Composer metadata');
        }

        return new Version\Composer($versionString, $version);
    }

    /**
     * See https://github.com/composer/metadata-minifier/blob/main/src/MetadataMinifier.php#L17 for source.
     *
     * @author Composer
     * @license MIT
     * @see https://github.com/composer/metadata-minifier/blob/HEAD/LICENSE
     *
     * @param list<mixed> $versions
     *
     * @return list<array<mixed>>
     */
    private static function expand(array $versions)
    {
        $expanded = [];
        $expandedVersion = null;
        foreach ($versions as $versionData) {
            if (!is_array($versionData)) {
                continue;
            }
            if (!$expandedVersion) {
                $expandedVersion = $versionData;
                $expanded[] = $expandedVersion;
                continue;
            }
            // add any changes from the previous version to the expanded one
            foreach ($versionData as $key => $val) {
                if ($val === '__unset') {
                    unset($expandedVersion[$key]);
                } else {
                    $expandedVersion[$key] = $val;
                }
            }
            $expanded[] = $expandedVersion;
        }

        return $expanded;
    }
}
