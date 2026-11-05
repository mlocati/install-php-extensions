<?php

namespace IPE\CLI;

use IPE\Exception;
use IPE\Extension;
use IPE\Extension\Asked;
use IPE\Extension\Version;
use IPE\Filesystem;

/**
 * @internal
 */
final class ArgumentsParser
{
    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    public function __construct(Filesystem $fs)
    {
        $this->fs = $fs;
    }

    /**
     * @param list<string> $arguments
     *
     * @param array<string, array<string, string|true>> $optionsByHandle the options specified for the extensions, keyed by extension handle or Composer package name
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return list<\IPE\Extension\Asked>
     */
    public function parseArguments(array $arguments, array $optionsByHandle = [])
    {
        $result = [];
        foreach (array_unique($arguments, SORT_STRING) as $argument) {
            if ($argument !== '') {
                $result[] = $this->parseArgument($argument, $optionsByHandle);
            }
        }

        return $result;
    }

    /**
     * @param non-empty-string $argument
     * @param array<string, array<string, string|true>> $optionsByHandle the options specified for the extensions, keyed by extension handle or Composer package name
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return \IPE\Extension\Asked
     */
    public function parseArgument($argument, array $optionsByHandle = [])
    {
        $result = $this->tryGitHubArgument($argument)
            ?: $this->tryURLArgument($argument)
            ?: $this->tryDirectoryArgument($argument)
            ?: $this->tryComposerArgument($argument, $optionsByHandle)
            ?: $this->tryHandleArgument($argument, $optionsByHandle);
        if ($result === null) {
            throw new Exception\InvalidArgument("Unrecognized argument: '{$argument}'");
        }

        return $result;
    }

    /**
     * @param non-empty-string $argument
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return \IPE\Extension\Asked\FromURL|null
     *
     * @example a specific commit (full commit SHA-1)
     *          gh:organization/repository@cafebabecafebabecafebabecafebabecafebabe
     *          gh:organization/repository@cafebabecafebabecafebabecafebabecafebabe#path/to/subdir
     * @example a specific commit (short commit SHA-1)
     *          gh:organization/repository@cafebabecafe
     *          gh:organization/repository@cafebabecafe#path/to/subdir
     * @example a tag
     *          gh:organization/repository@v3.2.0RC2
     *          gh:organization/repository@v3.2.0RC2#path/to/subdir
     *          gh:organization/repository@refs/tags/v3.2.0RC2
     *          gh:organization/repository@refs/tags/v3.2.0RC2#path/to/subdir
     * @example a branch
     *          gh:organization/repository@main
     *          gh:organization/repository@main#path/to/subdir
     *          gh:organization/repository@refs/heads/main
     *          gh:organization/repository@refs/heads/main#path/to/subdir
     */
    private function tryGitHubArgument($argument)
    {
        if (substr($argument, 0, 3) !== 'gh:') {
            return null;
        }
        list($base, $subdir) = $this->extractSubdirFromUrlLike($argument);
        $matches = null;
        if (!preg_match('#^gh:(?<organization>[A-Za-z0-9\-]{1,39})/(?<repository>[A-Za-z0-9.\-_]{1,100})@(?<ref>\S+)$#D', $base, $matches)) {
            throw new Exception\InvalidArgument("'{$base}' is not a valid GitHub shorthand");
        }
        $url = "https://codeload.github.com/{$matches['organization']}/{$matches['repository']}/tar.gz/{$matches['ref']}";

        return new Asked\FromURL($url, $subdir);
    }

    /**
     * @param non-empty-string $argument
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return \IPE\Extension\Asked|null
     */
    private function tryURLArgument($argument)
    {
        if (!preg_match('#^(http|https)://#i', $argument)) {
            return null;
        }
        list($base, $subdir) = $this->extractSubdirFromUrlLike($argument);

        return new Asked\FromURL($base, $subdir);
    }

    /**
     * @param non-empty-string $argument
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return \IPE\Extension\Asked\FromDirectory|null
     */
    private function tryDirectoryArgument($argument)
    {
        $starts = [
            preg_quote('/', '/'),
            preg_quote('./', '/'),
            preg_quote('../', '/'),
        ];
        if ($this->fs->getDirectorySeparator() === '\\') {
            $starts = array_merge($starts, [
                preg_quote('\\', '/'),
                preg_quote('.\\', '/'),
                preg_quote('..\\', '/'),
                '[A-Za-z]' . preg_quote(':/', '/'),
                '[A-Za-z]' . preg_quote(':\\', '/'),
            ]);
        }
        if (!preg_match('#^((' . implode(')|(', $starts) . '))#', $argument)) {
            return null;
        }
        if (!$this->fs->exists($argument)) {
            throw new Exception\InvalidArgument("Unable to find the directory '{$argument}'");
        }
        $path = $argument;
        try {
            $path = $this->fs->getRealPath($argument);
            if (!$this->fs->isReadable($path)) {
                throw new Exception\InvalidArgument("'{$path}' is not a readable");
            }
            if (!$this->fs->isDirectory($path)) {
                throw new Exception\InvalidArgument("'{$path}' is not a directory");
            }
        } catch (Exception\IO $x) {
            throw new Exception\InvalidArgument("Error checking the path '{$path}': {$x->getMessage()}");
        }

        return new Asked\FromDirectory($path);
    }

    /**
     * @param non-empty-string $argument
     * @param array<string, array<string, string|true>> $optionsByHandle
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return \IPE\Extension\Asked\FromComposer|null
     */
    private function tryComposerArgument($argument, array $optionsByHandle)
    {
        list($fullName, $versionRequirements) = explode(':', $argument, 2) + [1 => ''];
        $vendorAndName = explode('/', $fullName);
        if (
            count($vendorAndName) !== 2
            || !preg_match(Version\Lister\Composer::RX_VALID_VENDOR, $vendorAndName[0])
            || !preg_match(Version\Lister\Composer::RX_VALID_NAME, $vendorAndName[1])
        ) {
            return null;
        }

        return new Asked\FromComposer($fullName, $versionRequirements, isset($optionsByHandle[$fullName]) ? $optionsByHandle[$fullName] : []);
    }

    /**
     * @param non-empty-string $argument
     * @param array<string, array<string, string|true>> $optionsByHandle
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return \IPE\Extension\Asked\FromHandle|null
     */
    private function tryHandleArgument($argument, array $optionsByHandle)
    {
        list($handle, $versionRequirements) = explode(':', $argument, 2) + [1 => ''];
        if (!preg_match(Extension::RX_VALID_HANDLE, $handle)) {
            return null;
        }

        return new Asked\FromHandle($handle, $versionRequirements, isset($optionsByHandle[$handle]) ? $optionsByHandle[$handle] : []);
    }

    /**
     * @param non-empty-string $urlLike
     *
     * @return array{string, string}
     */
    private function extractSubdirFromUrlLike($urlLike)
    {
        $pHash = strrpos($urlLike, '#');
        if ($pHash === false || $pHash === 0) {
            return [$urlLike, ''];
        }

        return [substr($urlLike, 0, $pHash), substr($urlLike, $pHash + 1)];
    }
}
