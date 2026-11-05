<?php

namespace IPE\Extension\Source;

use Composer\Semver\Semver;
use IPE\Extension\Version;
use IPE\Filesystem\ArchiveFetcher;
use IPE\Filesystem\ArchiveFormat;
use IPE\Installer\State;
use IPE\PHPInfo;

/**
 * Extensions published on https://pecl.php.net/.
 */
final class Pecl
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var \IPE\Extension\Version\Lister\Pecl
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

    public function __construct(Version\Lister\Pecl $lister, Version\Selector $selector, ArchiveFetcher $archiveFetcher, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->lister = $lister;
        $this->selector = $selector;
        $this->archiveFetcher = $archiveFetcher;
    }

    /**
     * Select the newest version compatible with PHP.
     * The newest one is usually fine, and its requirements are in a small file: the big file with the requirements of all the versions is needed only if it's not compatible.
     *
     * @param string $peclName
     * @param non-empty-string $category
     * @param string $versionRequirements
     * @param \IPE\PHPInfo|null $php null to not check the compatibility with PHP
     * @param (callable(\IPE\Extension\Version\Pecl): bool)|null $filter the versions that can be used
     * @param string|null $whyNone set to the reason why no version has been selected
     *
     * @param-out string $whyNone
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Extension\Version\Pecl|null
     */
    public function selectVersion($peclName, $category, $versionRequirements, $php = null, $filter = null, &$whyNone = null)
    {
        $whyNone = '';
        $versions = $this->lister->getVersions($peclName);
        $usable = $filter === null ? $versions : array_values(array_filter($versions, $filter));
        $candidates = $usable;
        $version = null;
        $isNewest = true;
        $newestIncompatible = '';
        while (($candidate = $this->selector->select($candidates, $versionRequirements)) !== null) {
            $requirement = $php === null ? '' : $this->getPHPRequirement($candidate, $category, $isNewest);
            if ($php === null || $this->isCompatibleWithPHP($candidate, $requirement, $php)) {
                $version = $candidate;
                break;
            }
            if ($isNewest) {
                $newestIncompatible = "the newest, {$candidate}, requires PHP {$requirement}";
            }
            $isNewest = false;
            $candidates = array_filter($candidates, static function (Version\Pecl $item) use ($candidate) {
                return $item !== $candidate;
            });
        }
        if ($version === null) {
            if ($versions === []) {
                $whyNone = 'there are no versions';
            } elseif ($usable === []) {
                $whyNone = 'none of the versions can be used';
            } elseif ($php !== null && $newestIncompatible !== '') {
                $whyNone = "no version is compatible with PHP {$php->getVersion()} ({$newestIncompatible})";
            } else {
                $whyNone = $this->selector->explainNoMatch($usable, $versionRequirements);
            }
        }
        $this->logger->debug(
            $version === null ? "No suitable version of {$peclName} found on PECL" : "Selected version {$version} of {$peclName} from PECL",
            ['available' => array_map('strval', $versions)]
        );

        return $version;
    }

    /**
     * @param non-empty-string $category
     * @param bool $isNewest
     *
     * @throws \IPE\Exception\IO
     *
     * @return string
     */
    private function getPHPRequirement(Version\Pecl $version, $category, $isNewest)
    {
        if (!$isNewest) {
            $requirements = $this->lister->getPHPRequirements($version->getPeclName(), $category);
            // The file of the category may not contain the version (eg if the package has been moved to another category)
            if (isset($requirements[$version->getVersion()])) {
                return $requirements[$version->getVersion()];
            }
        }

        return $this->lister->getPHPRequirement($version);
    }

    /**
     * @param string $requirement
     *
     * @return bool
     */
    private function isCompatibleWithPHP(Version\Pecl $version, $requirement, PHPInfo $php)
    {
        if ($requirement === '') {
            return true;
        }
        try {
            $compatible = Semver::satisfies($php->getBaseVersion(), $requirement);
        } catch (\UnexpectedValueException $x) {
            $this->logger->debug("Ignoring unparsable PHP requirement '{$requirement}' of version {$version}");

            return true;
        }
        if (!$compatible) {
            $this->logger->debug("Version {$version} of {$version->getPeclName()} requires PHP {$requirement}");
        }

        return $compatible;
    }

    /**
     * Download and extract the extension source code.
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     *
     * @return non-empty-string the directory containing the extension source code
     */
    public function fetch(Version\Pecl $version, State $state)
    {
        $sourceDir = $this->archiveFetcher->fetch($version->getDownloadURL(), ArchiveFormat::TAR_GZ, $state->getVolatileDir());

        return PeclTarball::findSourceDir($sourceDir, $state->getFilesystem());
    }
}
