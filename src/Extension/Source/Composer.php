<?php

namespace IPE\Extension\Source;

use Composer\Semver\Semver;
use IPE\Exception;
use IPE\Extension\Version;
use IPE\Extension\Version\Stability;
use IPE\Filesystem\ArchiveFetcher;
use IPE\Filesystem\ArchiveFormat;
use IPE\Installer\State;
use IPE\PHPInfo;

/**
 * Extensions published as Composer packages (for example on https://packagist.org/extensions).
 */
final class Composer
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var \IPE\Extension\Version\Lister\Composer
     */
    private $lister;

    /**
     * @var \IPE\Extension\Version\Selector
     */
    private $selector;

    /**
     * @var \IPE\Filesystem\ArchiveFetcher
     */
    private $archiveFetcher;

    public function __construct(Version\Lister\Composer $lister, Version\Selector $selector, ArchiveFetcher $archiveFetcher, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->lister = $lister;
        $this->selector = $selector;
        $this->archiveFetcher = $archiveFetcher;
    }

    /**
     * Select the best version compatible with the PHP version.
     *
     * @param string $vendor
     * @param string $name
     * @param string $versionRequirements
     * @param \IPE\PHPInfo|null $php null to not check the compatibility with PHP
     * @param (callable(\IPE\Extension\Version\Composer): bool)|null $filter the versions that can be used
     * @param string|null $whyNone set to the reason why no version has been selected
     *
     * @param-out string $whyNone
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Extension\Version\Composer|null
     */
    public function selectVersion($vendor, $name, $versionRequirements, $php = null, $filter = null, &$whyNone = null)
    {
        $whyNone = '';
        list(, $minimumStability) = Version\Selector::parseRequirements($versionRequirements);
        $versions = $this->lister->getVersions($vendor, $name, $minimumStability === Stability::DEV);
        $usable = $filter === null ? $versions : array_values(array_filter($versions, $filter));
        $compatible = $php === null ? $usable : array_values(array_filter(
            $usable,
            function (Version\Composer $version) use ($php) {
                return $this->isCompatibleWithPHP($version, $php);
            }
        ));
        $version = $this->selector->select($compatible, $versionRequirements);
        if ($version === null) {
            $whyNone = $this->explainNoVersion($versions, $usable, $versionRequirements, $php);
        }
        $this->logger->debug(
            $version === null ? "No suitable version of {$vendor}/{$name} found in the Composer repositories" : "Selected version {$version} of {$vendor}/{$name} from the Composer repositories",
            ['available' => array_map('strval', $versions), 'compatible' => array_map('strval', $compatible)]
        );

        return $version;
    }

    /**
     * Download and extract the package.
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     *
     * @return non-empty-string the package root directory (the build-path declared in composer.json is not applied)
     */
    public function fetch(Version\Composer $version, State $state)
    {
        $failures = [];
        foreach ($version->getDownloadMethods() as $method) {
            switch ($method) {
                case Version\Composer::DOWNLOADMETHOD_COMPOSER:
                    $url = $version->getDownloadURL();
                    if ($url === '') {
                        $failures[] = "{$method}: no download URL";
                        break;
                    }
                    $type = $version->getDownloadType();
                    switch ($type) {
                        case 'zip':
                            return $this->archiveFetcher->fetch($url, ArchiveFormat::ZIP, $state->getVolatileDir());
                        case 'tar':
                        case '':
                            // The type doesn't tell the compression
                            return $this->archiveFetcher->fetch($url, '', $state->getVolatileDir());
                    }
                    $failures[] = "{$method}: unsupported archive type ({$type})";
                    break;
                case Version\Composer::DOWNLOADMETHOD_PREPACKAGED_SOURCE:
                    $sourceDir = $this->fetchPrePackagedSource($version, $state);
                    if ($sourceDir !== '') {
                        return $sourceDir;
                    }
                    $failures[] = "{$method}: no source archive attached to the GitHub release";
                    break;
                default:
                    $this->logger->debug("Skipping the unsupported download method {$method} of version {$version->getVersion()}");
                    $failures[] = "{$method}: not supported";
                    break;
            }
        }

        throw new Exception\IO("Unable to download version {$version->getVersion()} of {$version->getPackageName()}:\n- " . implode("\n- ", $failures));
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     *
     * @return string empty string if not available
     */
    private function fetchPrePackagedSource(Version\Composer $version, State $state)
    {
        $repository = $version->getGitHubRepository();
        if ($repository === '') {
            return '';
        }
        foreach ($version->getPrePackagedSourceAssetNames() as $assetName) {
            $url = "https://github.com/{$repository}/releases/download/" . rawurlencode($version->getVersion()) . '/' . rawurlencode($assetName);
            try {
                $extractedDir = $this->archiveFetcher->fetch($url, substr($assetName, -4) === '.zip' ? ArchiveFormat::ZIP : ArchiveFormat::TAR_GZ, $state->getVolatileDir());
            } catch (Exception\IO\NotFound $_) {
                continue;
            }

            // Like PIE, the declared build-path is relative to the root of the archive
            return $version->getBuildPath() === '' ? PeclTarball::findSourceDir($extractedDir, $state->getFilesystem()) : $extractedDir;
        }

        return '';
    }

    /**
     * @param \IPE\Extension\Version\Composer[] $versions all the versions
     * @param \IPE\Extension\Version\Composer[] $usable the versions accepted by the filter
     * @param string $versionRequirements
     * @param \IPE\PHPInfo|null $php
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return string
     */
    private function explainNoVersion(array $versions, array $usable, $versionRequirements, $php = null)
    {
        if ($versions === []) {
            list(, $minimumStability) = Version\Selector::parseRequirements($versionRequirements);

            return $minimumStability === Stability::DEV ? 'there are no versions' : 'there are no tagged versions';
        }
        if ($usable === []) {
            return 'none of the versions can be used';
        }
        $reason = $this->selector->explainNoMatch($usable, $versionRequirements);
        if ($reason !== '' || $php === null) {
            return $reason;
        }
        $newest = $this->selector->select($usable, $versionRequirements);

        return "no version is compatible with PHP {$php->getVersion()}" . ($newest === null ? '' : " (the newest, {$newest}, requires PHP {$newest->getPHPRequirement()})");
    }

    /**
     * @return bool
     */
    private function isCompatibleWithPHP(Version\Composer $version, PHPInfo $php)
    {
        $requirement = $version->getPHPRequirement();
        if ($requirement === '') {
            return true;
        }
        try {
            return Semver::satisfies($php->getBaseVersion(), $requirement);
        } catch (\UnexpectedValueException $x) {
            $this->logger->debug("Ignoring unparsable PHP requirement '{$requirement}' of version {$version->getVersion()}");

            return true;
        }
    }
}
