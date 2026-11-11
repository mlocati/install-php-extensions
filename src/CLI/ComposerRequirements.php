<?php

namespace IPE\CLI;

use IPE\Exception;
use IPE\Extension;
use IPE\Extension\Asked;
use IPE\Filesystem;
use IPE\PHPInfo;
use IPE\Service\JSON;
use IPE\Service\Regex;

/**
 * Determines the PHP extensions required by a project, by reading its composer.json and/or composer.lock files.
 */
class ComposerRequirements
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var \IPE\PHPInfo
     */
    private $php;

    /**
     * @var \IPE\Extension\Registry
     */
    private $registry;

    /**
     * @var \IPE\Extension\Asked\Resolver\Options
     */
    private $options;

    public function __construct(Filesystem $fs, PHPInfo $php, Extension\Registry $registry, Asked\Resolver\Options $options, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->fs = $fs;
        $this->php = $php;
        $this->registry = $registry;
        $this->options = $options;
    }

    /**
     * @param string $composerJson the path of the composer.json file (empty string to not read it)
     * @param string $composerLock the path of the composer.lock file (empty string to not read it)
     * @param bool $includeDev include the extensions listed in the require-dev of the project?
     * @param \IPE\Extension\Asked[] $alreadyAsked the extensions specified by the user: they win over the ones found in the files
     * @param array<string, array<string, string|true>> $optionsByHandle the options specified for the extensions, keyed by extension handle
     *
     * @throws \IPE\Exception\UnsupportedExtension
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return list<\IPE\Extension\Asked\FromHandle> the extensions that are not already installed
     */
    public function getAskedExtensions($composerJson, $composerLock, $includeDev, array $alreadyAsked = [], array $optionsByHandle = [])
    {
        $requirements = [];
        if ($composerJson !== '') {
            $requirements = $this->readComposerJson($composerJson, $includeDev);
        }
        if ($composerLock !== '') {
            $requirements = self::mergeRequirements($requirements, $this->readComposerLock($composerLock, $includeDev));
        }
        $result = [];
        foreach ($requirements as $name => $constraints) {
            if ($this->isInstalled($name)) {
                continue;
            }
            $known = $this->findKnownExtension($name);
            if ($known === null) {
                $message = "The extension {$name} is required by the composer files, but it is not installed and is unknown";
                if (!$this->options->isIgnoreUnknownExtensions()) {
                    throw new Exception\UnsupportedExtension($message);
                }
                $this->logger->warning($message);
                continue;
            }
            if ($this->php->isExtensionInstalled($known) || $this->isAlreadyAsked($known, $alreadyAsked)) {
                continue;
            }
            $handle = $known::getHandle();
            $result[] = new Asked\FromHandle($handle, self::mergeConstraints($constraints), isset($optionsByHandle[$handle]) ? $optionsByHandle[$handle] : []);
        }

        return $result;
    }

    /**
     * @param non-empty-string $path
     * @param bool $includeDev
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return array<string, list<string>> the keys are the names of the extensions, the values are composer version constraints
     */
    private function readComposerJson($path, $includeDev)
    {
        $data = $this->readJson($path);
        $requirements = self::extractExtensions(self::getSection($data, 'require'));
        if ($includeDev) {
            $requirements = self::mergeRequirements($requirements, self::extractExtensions(self::getSection($data, 'require-dev')));
        }

        return array_diff_key($requirements, self::extractExtensions(self::getSection($data, 'provide')), self::extractExtensions(self::getSection($data, 'replace')));
    }

    /**
     * @param non-empty-string $path
     * @param bool $includeDev
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return array<string, list<string>> the keys are the names of the extensions, the values are composer version constraints
     */
    private function readComposerLock($path, $includeDev)
    {
        $data = $this->readJson($path);
        $requirements = self::extractExtensions(self::getSection($data, 'platform'));
        if ($includeDev) {
            $requirements = self::mergeRequirements($requirements, self::extractExtensions(self::getSection($data, 'platform-dev')));
        }
        $provided = [];
        foreach (self::getSection($data, 'packages') as $package) {
            if (!is_array($package)) {
                continue;
            }
            $requirements = self::mergeRequirements($requirements, self::extractExtensions(self::getSection($package, 'require')));
            $provided += self::extractExtensions(self::getSection($package, 'provide')) + self::extractExtensions(self::getSection($package, 'replace'));
        }

        return array_diff_key($requirements, $provided);
    }

    /**
     * @param non-empty-string $path
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return array<mixed>
     */
    private function readJson($path)
    {
        $json = $this->fs->readFile($path);
        try {
            $data = JSON::decode($json, true);
        } catch (Exception\JSON $x) {
            throw new Exception\InvalidArgument("Failed to decode the file {$path}:\n{$x->getMessage()}");
        }
        if (!is_array($data)) {
            throw new Exception\InvalidArgument("Failed to decode the file {$path}");
        }

        return $data;
    }

    /**
     * @param array<mixed> $data
     * @param string $key
     *
     * @return array<mixed>
     */
    private static function getSection(array $data, $key)
    {
        return isset($data[$key]) && is_array($data[$key]) ? $data[$key] : [];
    }

    /**
     * @param array<mixed> $links the composer links, keyed by package name
     *
     * @return array<string, list<string>>
     */
    private static function extractExtensions(array $links)
    {
        $result = [];
        foreach ($links as $name => $constraint) {
            if (is_string($name) && stripos($name, 'ext-') === 0 && strlen($name) > 4) {
                $result[self::normalizeName(substr($name, 4))] = [is_string($constraint) ? trim($constraint) : '*'];
            }
        }

        return $result;
    }

    /**
     * @param array<string, list<string>> $first
     * @param array<string, list<string>> $second
     *
     * @return array<string, list<string>>
     */
    private static function mergeRequirements(array $first, array $second)
    {
        foreach ($second as $name => $constraints) {
            $first[$name] = isset($first[$name]) ? array_merge($first[$name], $constraints) : $constraints;
        }

        return $first;
    }

    /**
     * Composer writes the extension names in lower case and with dashes instead of spaces (eg zend-opcache for Zend OPcache).
     *
     * @param string $name
     *
     * @return string
     */
    private static function normalizeName($name)
    {
        return strtolower(str_replace(' ', '-', $name));
    }

    /**
     * @param string $name a normalized name
     *
     * @return bool
     */
    private function isInstalled($name)
    {
        foreach ($this->php->getInstalledExtensions() as $installed) {
            if (self::normalizeName($installed) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $name a normalized name
     *
     * @return \IPE\Extension|null
     */
    private function findKnownExtension($name)
    {
        foreach ($this->registry->getExtensionClasses() as $class) {
            foreach (array_merge([$class::getHandle()], $class::getInstalledNames()) as $candidate) {
                if (self::normalizeName($candidate) === $name) {
                    return $this->registry->getExtensionByHandle($class::getHandle());
                }
            }
        }

        return null;
    }

    /**
     * @param \IPE\Extension\Asked[] $alreadyAsked
     *
     * @return bool
     */
    private function isAlreadyAsked(Extension $extension, array $alreadyAsked)
    {
        foreach ($alreadyAsked as $asked) {
            if ($asked instanceof Asked\FromHandle && $asked->getHandle() === $extension::getHandle()) {
                return true;
            }
            if ($asked instanceof Asked\FromComposer) {
                $known = $this->registry->getExtensionByComposerPackageName($asked->getFullName());
                if ($known !== null && $known::getHandle() === $extension::getHandle()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Build a constraint that satisfies all the specified ones.
     * Composer gives the AND operator a higher precedence than OR and has no parentheses: A || B, C means A || (B, C).
     * So, to combine A || B with C we need A, C || B, C.
     *
     * @param list<string> $constraints
     *
     * @return string empty string if there's no actual constraint
     */
    private static function mergeConstraints(array $constraints)
    {
        $alternatives = [''];
        foreach (array_unique($constraints) as $constraint) {
            if ($constraint === '' || $constraint === '*') {
                continue;
            }
            $merged = [];
            foreach ($alternatives as $alternative) {
                foreach (Regex::split('/\s*\|\|?\s*/', $constraint) as $part) {
                    $merged[] = $alternative === '' ? $part : "{$alternative}, {$part}";
                }
            }
            $alternatives = $merged;
        }

        return implode(' || ', $alternatives);
    }
}
