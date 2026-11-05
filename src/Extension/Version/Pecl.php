<?php

namespace IPE\Extension\Version;

use Composer\Semver\VersionParser;
use IPE\Exception;
use IPE\Extension\Version;

final class Pecl extends Version
{
    /**
     * @var non-empty-string
     */
    private $peclName;

    /**
     * @param non-empty-string $peclName
     * @param non-empty-string $version
     * @param non-empty-string $peclStability
     */
    public function __construct($peclName, $version, $peclStability)
    {
        $stability = self::normalizeStability($peclStability);
        // Some releases are flagged as stable even if their version says otherwise (for example igbinary 3.2.17RC1)
        $versionStability = VersionParser::parseStability($version);
        parent::__construct($version, Stability::compare($versionStability, $stability) < 0 ? $versionStability : $stability);
        $this->peclName = $peclName;
    }

    /**
     * @return non-empty-string
     */
    public function getPeclName()
    {
        return $this->peclName;
    }

    /**
     * @return non-empty-string
     */
    public function getDownloadURL()
    {
        // The file names are case sensitive (eg LZF-1.7.0.tgz), this form is not
        return "https://pecl.php.net/get/{$this->peclName}/{$this->getVersion()}";
    }

    /**
     * @param non-empty-string $peclStability
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return non-empty-string
     */
    private static function normalizeStability($peclStability)
    {
        switch (strtolower($peclStability)) {
            case 'stable':
                return Stability::STABLE;
            case 'beta':
                return Stability::BETA;
            case 'alpha':
                return Stability::ALPHA;
            case 'devel':
                return Stability::DEV;
            case 'snapshot':
                return Stability::DEV;
        }
        throw new Exception\InvalidArgument("Invalid PECL stability: '{$peclStability}'");
    }
}
