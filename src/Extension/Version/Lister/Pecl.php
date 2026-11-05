<?php

namespace IPE\Extension\Version\Lister;

use IPE\Exception;
use IPE\Extension\Version;
use IPE\Filesystem\Downloader;
use IPE\Service\BasicXmlParser;

class Pecl
{
    /**
     * @public
     * @var string
     * @see https://github.com/php/web-pecl/blob/ea9bd0fb5ded97edac46c4aadc74dbf9236bf61d/config/app.php#L128
     */
    const RX_VALID_NAME = '#^[A-Za-z][A-Za-z0-9_]+$#D';

    /**
     * @var \IPE\Filesystem\Downloader
     */
    private $downloader;

    /**
     * @var array<string, array<string, array<string, string>>> the keys are the categories, the values are the result of parsePackagesInfo()
     */
    private $categories = [];

    public function __construct(Downloader $downloader)
    {
        $this->downloader = $downloader;
    }

    /**
     * @param string $package
     * @param bool $includeStable
     * @param bool $includeDev
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Extension\Version\Pecl[]
     */
    public function getVersions($package, $includeStable = true, $includeDev = true)
    {
        if (!preg_match(self::RX_VALID_NAME, $package)) {
            throw new Exception\InvalidArgument('$package is not valid');
        }
        $url = 'https://pecl.php.net/rest/r/' . strtolower($package) . '/allreleases.xml';
        try {
            $xml = $this->downloader->getContent($url);
        } catch (Exception\IO\NotFound $_) {
            return [];
        }
        $versions = [];
        $vs = [];
        $parser = new BasicXmlParser();
        $parser->listElementsWithTextContent($xml, static function (array $path, $text) use ($package, &$vs, &$versions) {
            if ($path === ['a', 'r', 'v']) {
                if ($vs !== []) {
                    throw new Exception\InvalidArgument('Invalid/unexpected PECL xml');
                }
                $vs = [$text];
            } elseif ($path === ['a', 'r', 's']) {
                if (count($vs) !== 1) {
                    throw new Exception\InvalidArgument('Invalid/unexpected PECL xml');
                }
                $versions[] = new Version\Pecl($package, $vs[0], $text);
                $vs = [];
            } elseif ($vs !== []) {
                throw new Exception\InvalidArgument('Invalid/unexpected PECL xml');
            }
        });
        if ($vs !== []) {
            throw new Exception\InvalidArgument('Invalid/unexpected PECL xml');
        }

        return $versions;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return string a composer version constraint (empty string if not specified or not understood)
     */
    public function getPHPRequirement(Version\Pecl $version)
    {
        $url = 'https://pecl.php.net/rest/r/' . strtolower($version->getPeclName()) . "/deps.{$version->getVersion()}.txt";
        try {
            $serialized = $this->downloader->getContent($url);
        } catch (Exception\IO\NotFound $_) {
            return '';
        }

        return self::buildConstraint($serialized);
    }

    /**
     * Get the requirements of all the versions of a package, from the file of its category (which is big, so it's downloaded only once).
     *
     * @param string $package
     * @param non-empty-string $category
     *
     * @throws \IPE\Exception\IO
     *
     * @return array<string, string> the keys are the versions, the values are composer version constraints (empty strings if not specified or not understood)
     */
    public function getPHPRequirements($package, $category)
    {
        if (!isset($this->categories[$category])) {
            $this->categories[$category] = self::parsePackagesInfo($this->downloadPackagesInfo($category));
        }
        $package = strtolower($package);
        $result = [];
        foreach (isset($this->categories[$category][$package]) ? $this->categories[$category][$package] : [] as $version => $serialized) {
            $result[$version] = self::buildConstraint($serialized);
        }

        return $result;
    }

    /**
     * @param non-empty-string $category
     *
     * @throws \IPE\Exception\IO
     *
     * @return string
     */
    private function downloadPackagesInfo($category)
    {
        try {
            return $this->downloader->getContent('https://pecl.php.net/rest/c/' . str_replace(' ', '+', $category) . '/packagesinfo.xml');
        } catch (Exception\IO\NotFound $_) {
            return '';
        }
    }

    /**
     * It's not well-formed XML: the elements of the packages contain other XML declarations.
     *
     * @param string $xml
     *
     * @return array<string, array<string, string>> the keys are the lower case package names and the versions, the values are the serialized dependencies
     */
    private static function parsePackagesInfo($xml)
    {
        $result = [];
        $packages = null;
        preg_match_all('#<pi>(.*?)</pi>#s', $xml, $packages);
        foreach ($packages[1] as $package) {
            $name = null;
            if (!preg_match('#<n>([^<]+)</n>#', $package, $name)) {
                continue;
            }
            $dependencies = null;
            preg_match_all('#<deps>\s*<v>([^<]+)</v>\s*<d>(.*?)</d>\s*</deps>#s', $package, $dependencies, PREG_SET_ORDER);
            foreach ($dependencies as $dependency) {
                $result[strtolower(trim($name[1]))][trim($dependency[1])] = html_entity_decode($dependency[2], ENT_QUOTES, 'UTF-8');
            }
        }

        return $result;
    }

    /**
     * @param string $serialized the serialized dependencies of a version
     *
     * @return string
     */
    private static function buildConstraint($serialized)
    {
        // PHP < 7.0 doesn't have the allowed_classes option of unserialize()
        if (preg_match('/(^|[;{])[OC]:\d+:"/', $serialized)) {
            return '';
        }
        $dependencies = @unserialize($serialized);
        if (!is_array($dependencies)) {
            return '';
        }

        return isset($dependencies['required']) && is_array($dependencies['required'])
            ? self::buildConstraintV2($dependencies['required'])
            : self::buildConstraintV1($dependencies);
    }

    /**
     * @param array<mixed> $requiredDependencies the dependencies, in the format of package.xml 2.0
     *
     * @return string
     */
    private static function buildConstraintV2(array $requiredDependencies)
    {
        $php = isset($requiredDependencies['php']) && is_array($requiredDependencies['php']) ? $requiredDependencies['php'] : [];
        $parts = [];
        if (isset($php['min']) && is_string($php['min'])) {
            $parts[] = ">={$php['min']}";
        }
        if (isset($php['max']) && is_string($php['max'])) {
            $parts[] = "<={$php['max']}";
        }
        foreach (isset($php['exclude']) ? (array) $php['exclude'] : [] as $exclude) {
            if (is_string($exclude)) {
                $parts[] = "!={$exclude}";
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<mixed> $dependencies the dependencies, in the format of package.xml 1.0
     *
     * @return string
     */
    private static function buildConstraintV1(array $dependencies)
    {
        $operators = ['ge' => '>=', 'gt' => '>', 'le' => '<=', 'lt' => '<', 'eq' => '=', 'ne' => '!='];
        $parts = [];
        foreach ($dependencies as $dependency) {
            if (is_array($dependency) && isset($dependency['type'], $dependency['rel'], $dependency['version']) && $dependency['type'] === 'php' && is_string($dependency['rel']) && is_string($dependency['version']) && isset($operators[$dependency['rel']])) {
                $parts[] = $operators[$dependency['rel']] . $dependency['version'];
            }
        }

        return implode(' ', $parts);
    }
}
