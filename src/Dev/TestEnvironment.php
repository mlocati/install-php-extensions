<?php

namespace IPE\Dev;

/**
 * Where the CI tests install-php-extensions: a Docker image, or a GitHub Actions runner with PHP installed by Homebrew.
 *
 * @internal
 */
interface TestEnvironment
{
    /**
     * @return non-empty-string eg 'php:8.4-cli-alpine3.21' or 'php@8.4'
     */
    public function getName();

    /**
     * @return non-empty-string eg 'alpine3.21' or 'macos'
     */
    public function getOS();

    /**
     * @return non-empty-string major.minor
     */
    public function getPHPVersion();

    /**
     * @return bool true for thread-safe builds of PHP
     */
    public function isZTS();

    /**
     * How many times the CI installs every extension.
     *
     * @return int
     */
    public function getInstallationsPerExtension();

    /**
     * @return non-empty-string the label of the GitHub Actions runner
     */
    public function getRunsOn();
}
