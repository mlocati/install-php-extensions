<?php

namespace IPE\Dev;

/**
 * Where the CI tests install-php-extensions.
 *
 * @internal
 */
final class TestEnvironments
{
    /**
     * The operating systems of DockerImages::getOperatingSystems(), then macOS.
     *
     * @return non-empty-list<non-empty-string>
     */
    public static function getOperatingSystems()
    {
        return array_merge(DockerImages::getOperatingSystems(), [HomebrewFormula::OS]);
    }

    /**
     * @return list<\IPE\Dev\TestEnvironment> sorted like getOperatingSystems()
     */
    public static function getAll()
    {
        return array_merge(DockerImages::getAll(), self::getHomebrewFormulae());
    }

    /**
     * @param string $phpVersion major.minor
     * @param bool $zts
     *
     * @return list<\IPE\Dev\TestEnvironment> sorted like getOperatingSystems()
     */
    public static function find($phpVersion, $zts)
    {
        return array_values(array_filter(self::getAll(), static function (TestEnvironment $environment) use ($phpVersion, $zts) {
            return $environment->getPHPVersion() === $phpVersion && $environment->isZTS() === $zts;
        }));
    }

    /**
     * The PHP formulae of homebrew/core.
     *
     * @return list<\IPE\Dev\HomebrewFormula> sorted by PHP version
     */
    private static function getHomebrewFormulae()
    {
        return [
            new HomebrewFormula('php@8.4', '8.4'),
            new HomebrewFormula('php@8.5', '8.5'),
        ];
    }
}
