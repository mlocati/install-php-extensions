<?php

namespace IPE\Extension;

use IPE\Exception;
use IPE\OS;
use IPE\PHPInfo;

/**
 * An environment is the identifier of an operating system, optionally followed by its version or by an inclusive range of versions, optionally followed by the PHP version, optionally followed by "-zts" or "-nts" (to match only thread-safe or non-thread-safe PHP).
 *
 * @example 'alpine', 'alpine3.14', 'debian', 'debian12', 'alpine-php7', 'alpine-php7.2', 'alpine3.9-php7.1', 'ubuntu24.04-php8.3', 'debian12-zts', 'alpine3.17-php8.2-nts', 'alpine3.10..3.16', 'debian10..11-zts'
 */
class UnsupportedEnvironment
{
    /**
     * @var \IPE\OS\Factory
     */
    private $osFactory;

    public function __construct(OS\Factory $osFactory)
    {
        $this->osFactory = $osFactory;
    }

    /**
     * @param string $environment
     *
     * @return bool
     */
    public function isValid($environment)
    {
        return $this->parse($environment) !== null;
    }

    /**
     * @param string $environment
     *
     * @throws \IPE\Exception\InvalidArgument if $environment is not valid
     *
     * @return bool
     */
    public function matches($environment, PHPInfo $php, OS $os)
    {
        $parsed = $this->parse($environment);
        if ($parsed === null) {
            throw new Exception\InvalidArgument("Invalid environment: '{$environment}'");
        }
        list($osClass, $osVersionFrom, $osVersionTo, $phpVersion, $threadSafety) = $parsed;

        return $os instanceof $osClass
            && ($osVersionFrom === null || ($osVersionFrom <= $os->getNumericVersion() && $os->getNumericVersion() <= $osVersionTo))
            && self::matchesPHP($phpVersion, $php)
            && ($threadSafety === '' || ($threadSafety === 'zts') === $php->isThreadSafe());
    }

    /**
     * @param string $environment
     *
     * @return array{class-string<\IPE\OS>, positive-int|null, positive-int|null, string, string}|null the OS class, its first and last numeric versions, the PHP version, and the thread safety ('zts', 'nts', or empty for both)
     */
    private function parse($environment)
    {
        $match = null;
        if (!preg_match('/^(?<os>[a-z]+)(?:(?<osVersion>\d+(?:\.\d+)*)(?:\.\.(?<osVersionTo>\d+(?:\.\d+)*))?)?(?:-php(?<php>\d+(?:\.\d+)?))?(?:-(?<threadSafety>nts|zts))?$/D', $environment, $match)) {
            return null;
        }
        $osClass = $this->osFactory->getOSClassByIdentifier($match['os']);
        if ($osClass === '') {
            return null;
        }
        $osVersionFrom = null;
        $osVersionTo = null;
        if (isset($match['osVersion']) && $match['osVersion'] !== '') {
            $osVersionFrom = $osClass::parseNumericVersion($match['osVersion']);
            if ($osVersionFrom === null) {
                return null;
            }
            $osVersionTo = $osVersionFrom;
            if (isset($match['osVersionTo']) && $match['osVersionTo'] !== '') {
                $osVersionTo = $osClass::parseNumericVersion($match['osVersionTo']);
                if ($osVersionTo === null || $osVersionTo <= $osVersionFrom) {
                    return null;
                }
            }
        }

        return [$osClass, $osVersionFrom, $osVersionTo, isset($match['php']) ? $match['php'] : '', isset($match['threadSafety']) ? $match['threadSafety'] : ''];
    }

    /**
     * @param string $version empty string, major version, or major.minor version
     *
     * @return bool
     */
    private static function matchesPHP($version, PHPInfo $php)
    {
        if ($version === '') {
            return true;
        }
        $chunks = explode('.', $version);
        if (count($chunks) === 1) {
            return (int) $chunks[0] === (int) ($php->getMajorMinorVersion() / 100);
        }

        return (int) $chunks[0] * 100 + (int) $chunks[1] === $php->getMajorMinorVersion();
    }
}
