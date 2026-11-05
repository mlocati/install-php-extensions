<?php

namespace IPE\Extension\Version;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use IPE\Exception;

/**
 * Picks the best version among the available ones, given the user requirements.
 */
final class Selector
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    public function __construct(\IPE\Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Split the requirements asked by the user (e.g. '^1.2@beta') into a composer constraint and a minimum stability.
     * Exact versions without an explicit stability are accepted whatever their stability.
     *
     * @param string $versionRequirements
     * @param non-empty-string $defaultMinimumStability
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return array{non-empty-string, non-empty-string} the composer constraint ('*' if empty) and the minimum stability
     */
    public static function parseRequirements($versionRequirements, $defaultMinimumStability = Stability::STABLE)
    {
        $constraint = trim($versionRequirements);
        $minimumStability = $defaultMinimumStability;
        $matches = null;
        if (preg_match('/^(?<constraint>.*?)@(?<stability>[a-z]+)$/i', $constraint, $matches)) {
            $minimumStability = Stability::normalize($matches['stability']);
            if ($minimumStability === '') {
                throw new Exception\InvalidArgument("Invalid stability in version requirements: '{$versionRequirements}'");
            }
            $constraint = trim($matches['constraint']);
        } elseif (self::isExactVersion($constraint)) {
            $minimumStability = Stability::DEV;
        }
        if ($constraint === '') {
            $constraint = '*';
        }
        try {
            (new VersionParser())->parseConstraints($constraint);
        } catch (\UnexpectedValueException $x) {
            throw new Exception\InvalidArgument("Invalid version requirements: '{$versionRequirements}'");
        }

        return [$constraint, $minimumStability];
    }

    /**
     * @param string $versionRequirements
     *
     * @return bool
     */
    public static function isExactVersion($versionRequirements)
    {
        return preg_match('/^\d[\w.+\-]*$/D', $versionRequirements) === 1;
    }

    /**
     * @template T of \IPE\Extension\Version
     *
     * @param T[] $versions
     * @param string $versionRequirements
     * @param non-empty-string $defaultMinimumStability
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return T|null
     */
    public function select(array $versions, $versionRequirements, $defaultMinimumStability = Stability::STABLE)
    {
        list($constraint, $minimumStability) = self::parseRequirements($versionRequirements, $defaultMinimumStability);
        $parser = new VersionParser();
        $best = null;
        $bestNormalized = '';
        foreach ($versions as $version) {
            if (Stability::compare($version->getStability(), $minimumStability) < 0) {
                continue;
            }
            try {
                $normalized = $parser->normalize($version->getVersion());
            } catch (\UnexpectedValueException $x) {
                $this->logger->debug("Skipping version with unparsable version string: {$version->getVersion()}");
                continue;
            }
            if (!Semver::satisfies($normalized, $constraint)) {
                continue;
            }
            if ($best === null || Comparator::greaterThan($normalized, $bestNormalized)) {
                $best = $version;
                $bestNormalized = $normalized;
            }
        }

        return $best;
    }

    /**
     * Explain why select() doesn't find a version.
     *
     * @param \IPE\Extension\Version[] $versions
     * @param string $versionRequirements
     * @param non-empty-string $defaultMinimumStability
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return string empty string if select() finds a version
     */
    public function explainNoMatch(array $versions, $versionRequirements, $defaultMinimumStability = Stability::STABLE)
    {
        if ($this->select($versions, $versionRequirements, $defaultMinimumStability) !== null) {
            return '';
        }
        list($constraint, $minimumStability) = self::parseRequirements($versionRequirements, $defaultMinimumStability);
        $newest = $this->select($versions, '', Stability::DEV);
        if ($newest === null) {
            return 'no versions are available';
        }
        $newestSatisfying = $this->select($versions, "{$constraint}@" . Stability::DEV);
        if ($newestSatisfying === null) {
            return "no version satisfies the version requirements (the newest is {$newest})";
        }

        return ($constraint === '*' ? 'all the versions are' : 'the versions that satisfy the version requirements are')
            . " less stable than {$minimumStability} (the newest is {$newestSatisfying}): use @{$newestSatisfying->getStability()} in the version requirements to accept it";
    }
}
