<?php

namespace IPE\Installer;

use IPE\Exception;
use IPE\Extension\GitSource;
use IPE\Extension\Resolved;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Filesystem\Downloader;

/**
 * Fetch the git submodules missing from the source code of the extensions.
 */
final class GitSubmodules
{
    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var \IPE\Filesystem\Downloader
     */
    private $downloader;

    /**
     * @var \IPE\Installer\BuildOptions
     */
    private $buildOptions;

    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var array<string, string|null> the contents of the downloaded .gitmodules files (empty string if missing, NULL if the download failed)
     */
    private $downloadedGitmodules = [];

    public function __construct(Filesystem $fs, Downloader $downloader, BuildOptions $buildOptions, \IPE\Logger $logger)
    {
        $this->fs = $fs;
        $this->downloader = $downloader;
        $this->buildOptions = $buildOptions;
        $this->logger = $logger;
    }

    /**
     * Do we need git to fetch the missing submodules of these extensions?
     *
     * @param \IPE\Extension\Resolved[] $resolvedExtensions
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    public function isGitNeeded(array $resolvedExtensions)
    {
        if (!$this->buildOptions->isFetchGitSubmodules()) {
            return false;
        }
        foreach ($resolvedExtensions as $resolvedExtension) {
            $gitSource = $resolvedExtension->getGitSource();
            if ($gitSource === null) {
                continue;
            }
            $missing = $this->findMissingSubmodules($resolvedExtension, $gitSource, false);
            if ($missing === null || ($missing !== [] && ($gitSource->getRemoteURL() !== '' || $this->isWorkingTree($gitSource->getRoot())))) {
                $this->logger->debug("git is needed to fetch the git submodules of {$resolvedExtension}");

                return true;
            }
        }

        return false;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function fetchMissing(Resolved $resolvedExtension, State $state)
    {
        if (!$this->buildOptions->isFetchGitSubmodules()) {
            return;
        }
        $gitSource = $resolvedExtension->getGitSource();
        if ($gitSource === null) {
            $this->logger->debug("Not looking for the git submodules of {$resolvedExtension}: its source code doesn't come from a local directory, a URL or a Composer package");

            return;
        }
        $missing = $this->findMissingSubmodules($resolvedExtension, $gitSource, true);
        if ($missing === []) {
            return;
        }
        $root = $gitSource->getRoot();
        try {
            if ($missing === null || $gitSource->getRemoteURL() !== '') {
                $this->fetchFromRepository($resolvedExtension, $gitSource, $missing, $state);
            } elseif ($this->isWorkingTree($root)) {
                $this->logger->info('Fetching ' . self::describe($resolvedExtension, $missing));
                // The directory may be owned by another user (for example, when it's mounted in a container).
                // Only the missing submodules, so that we don't change the commits of the other ones.
                $this->runGit(array_merge(['-c', "safe.directory={$root}", '-C', $root, 'submodule', 'update', '--init', '--recursive', '--'], $missing));
            } else {
                $this->logger->warning('Unable to fetch ' . self::describe($resolvedExtension, $missing) . ": {$root} is not a git working tree and its repository is unknown");
            }
        } catch (Exception\IO $x) {
            $this->logger->warning('Unable to fetch ' . self::describe($resolvedExtension, $missing) . ": {$x->getMessage()}");
        }
    }

    /**
     * @param list<non-empty-string>|null $paths
     *
     * @return string
     */
    private static function describe(Resolved $resolvedExtension, $paths)
    {
        $result = "the git submodules of {$resolvedExtension}";

        return $paths === null ? $result : ($result . ' (' . implode(', ', $paths) . ')');
    }

    /**
     * @param bool $log
     *
     * @throws \IPE\Exception\IO
     *
     * @return list<non-empty-string>|null the paths of the missing submodules, or NULL if we need to clone the repository to find them
     */
    private function findMissingSubmodules(Resolved $resolvedExtension, GitSource $gitSource, $log)
    {
        $root = $gitSource->getRoot();
        if ($this->fs->isFile("{$root}/.gitmodules")) {
            $gitmodules = "{$root}/.gitmodules";
            $paths = $this->parseSubmodulePaths($this->fs->readFile($gitmodules), $gitmodules, $log);
        } elseif ($gitSource->getRemoteURL() === '') {
            if ($log) {
                $this->logger->debug("There's no .gitmodules file in {$root}");
            }

            return [];
        } else {
            $gitmodules = $gitSource->getGitmodulesURL();
            $contents = $gitmodules === '' ? null : $this->downloadGitmodules($gitmodules);
            if ($contents === null) {
                if ($log) {
                    $this->logger->debug("There's no .gitmodules file in {$root}: we need to clone the repository to find the git submodules of {$resolvedExtension}");
                }

                return null;
            }
            if ($contents === '') {
                if ($log) {
                    $this->logger->debug("The repository of {$resolvedExtension} doesn't have a .gitmodules file");
                }

                return [];
            }
            $paths = $this->parseSubmodulePaths($contents, $gitmodules, $log);
        }

        return $this->findMissing($resolvedExtension, $root, $paths, $gitmodules, $log);
    }

    /**
     * @param non-empty-string $url
     *
     * @return string|null empty string if the repository doesn't have a .gitmodules file, NULL if the download failed
     */
    private function downloadGitmodules($url)
    {
        if (!array_key_exists($url, $this->downloadedGitmodules)) {
            try {
                $contents = $this->downloader->getContent($url);
            } catch (Exception\IO\NotFound $x) {
                $contents = '';
            } catch (Exception $x) {
                $this->logger->debug("Failed to download {$url}: {$x->getMessage()}");
                $contents = null;
            }
            $this->downloadedGitmodules[$url] = $contents;
        }

        return $this->downloadedGitmodules[$url];
    }

    /**
     * @param non-empty-string $root
     * @param list<non-empty-string> $paths
     * @param string $gitmodules where the paths come from
     * @param bool $log
     *
     * @throws \IPE\Exception\IO
     *
     * @return list<non-empty-string>
     */
    private function findMissing(Resolved $resolvedExtension, $root, array $paths, $gitmodules, $log)
    {
        if ($paths === []) {
            if ($log) {
                $this->logger->debug("No git submodules are declared in {$gitmodules}");
            }

            return [];
        }
        $missing = $this->findMissingPaths($root, $paths);
        if ($missing === [] && $log) {
            $this->logger->debug("The git submodules of {$resolvedExtension} are already present: " . implode(', ', $paths));
        }

        return $missing;
    }

    /**
     * @param non-empty-string $root
     * @param list<non-empty-string> $paths
     *
     * @throws \IPE\Exception\IO
     *
     * @return list<non-empty-string> the paths of the submodules that are missing
     */
    private function findMissingPaths($root, array $paths)
    {
        $missing = [];
        foreach ($paths as $path) {
            if (!$this->fs->isDirectory("{$root}/{$path}") || $this->fs->listDirectoryContents("{$root}/{$path}") === []) {
                $missing[] = $path;
            }
        }

        return $missing;
    }

    /**
     * We don't look for .gitmodules in the parent directories: they may belong to another repository.
     *
     * @param non-empty-string $root
     * @param bool $warnInvalid
     *
     * @throws \IPE\Exception\IO
     *
     * @return list<non-empty-string> the valid paths declared in the .gitmodules file of $root
     */
    private function readSubmodulePaths($root, $warnInvalid)
    {
        if (!$this->fs->isFile("{$root}/.gitmodules")) {
            return [];
        }

        return $this->parseSubmodulePaths($this->fs->readFile("{$root}/.gitmodules"), "{$root}/.gitmodules", $warnInvalid);
    }

    /**
     * @param string $contents the contents of a .gitmodules file
     * @param string $gitmodules where the contents come from
     * @param bool $warnInvalid
     *
     * @return list<non-empty-string> the valid paths
     */
    private function parseSubmodulePaths($contents, $gitmodules, $warnInvalid)
    {
        $matches = null;
        preg_match_all('/^\s*path\s*=\s*(.+?)\s*$/m', $contents, $matches);
        $result = [];
        foreach ($matches[1] as $declaredPath) {
            $path = rtrim($declaredPath, '/');
            if (self::isValidPath($path)) {
                $result[] = $path;
            } elseif ($warnInvalid) {
                $this->logger->warning("Ignoring the git submodule path '{$declaredPath}' of {$gitmodules}: it must be a relative path inside that directory");
            }
        }

        return $result;
    }

    /**
     * Only the checks that don't need git, which may not be installed yet.
     *
     * @param non-empty-string $root
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    private function isWorkingTree($root)
    {
        $git = "{$root}/.git";
        if ($this->fs->isDirectory($git)) {
            return $this->fs->isFile("{$git}/HEAD") && $this->fs->isDirectory("{$git}/objects") && $this->fs->isDirectory("{$git}/refs");
        }
        // The submodules and the additional worktrees have a .git file pointing to the actual git directory
        if ($this->fs->isFile($git)) {
            return $this->fs->readFile($git, strlen('gitdir:')) === 'gitdir:';
        }

        return false;
    }

    /**
     * The paths come from the source code of the extensions, and we delete and copy directories there.
     *
     * @param string $path
     *
     * @return bool
     *
     * @phpstan-assert-if-true non-empty-string $path
     */
    private static function isValidPath($path)
    {
        // Backslashes and quotes are escapes in .gitmodules, and the colons are drive letters or pathspec magic
        if ($path === '' || $path[0] === '/' || strpbrk($path, '\\":') !== false) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param non-empty-list<non-empty-string>|null $missing NULL if we need to look for them in the repository
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function fetchFromRepository(Resolved $resolvedExtension, GitSource $gitSource, $missing, State $state)
    {
        $root = $gitSource->getRoot();
        $url = $gitSource->getRemoteURL();
        $reference = $gitSource->getRemoteReference();
        $this->logger->info($missing === null ? ('Looking for ' . self::describe($resolvedExtension, null) . " in {$url}") : ('Fetching ' . self::describe($resolvedExtension, $missing) . " from {$url}"));
        $clone = $state->getVolatileDir()->createSubdir();
        $this->runGit(['init', '-q', $clone]);
        try {
            $this->runGit(['-C', $clone, 'fetch', '-q', '--depth', '1', $url, $reference === '' ? 'HEAD' : $reference]);
            $checkout = 'FETCH_HEAD';
        } catch (Exception\IO $x) {
            if ($reference === '') {
                throw $x;
            }
            // Abbreviated commits can't be fetched directly, and some servers don't allow fetching commits that aren't at the tip of a ref
            $this->logger->debug("Fetching the whole repository, since fetching {$reference} failed: {$x->getMessage()}");
            $this->runGit(['-C', $clone, 'fetch', '-q', '--update-head-ok', $url, '+refs/heads/*:refs/heads/*', '+refs/tags/*:refs/tags/*']);
            $checkout = $reference;
        }
        $this->runGit(['-C', $clone, '-c', 'advice.detachedHead=false', 'checkout', '-q', $checkout]);
        if ($missing === null) {
            $missing = $this->findMissing($resolvedExtension, $root, $this->readSubmodulePaths($clone, true), "{$clone}/.gitmodules", true);
            if ($missing === []) {
                return;
            }
            $this->logger->info('Fetching ' . self::describe($resolvedExtension, $missing));
        }
        $this->runGit(['-C', $clone, 'submodule', 'update', '-q', '--init', '--recursive', '--depth', '1']);
        foreach ($missing as $path) {
            $this->deleteGitFiles("{$clone}/{$path}");
            // Some archives don't include the empty directories of the submodules: we need at least their parents
            if (!$this->fs->isDirectory("{$root}/{$path}")) {
                $this->fs->createDirectory("{$root}/{$path}");
            }
            $this->fs->deleteDirectory("{$root}/{$path}");
            $this->fs->copyDirectory("{$clone}/{$path}", "{$root}/{$path}", true);
        }
    }

    /**
     * The .git files of the submodules (and of their submodules) point to the clone, which will be deleted.
     *
     * @param non-empty-string $directory
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function deleteGitFiles($directory)
    {
        if ($this->fs->isFile("{$directory}/.git")) {
            $this->fs->deleteFile("{$directory}/.git");
        }
        foreach ($this->readSubmodulePaths($directory, false) as $path) {
            if ($this->fs->isDirectory("{$directory}/{$path}")) {
                $this->deleteGitFiles("{$directory}/{$path}");
            }
        }
    }

    /**
     * @param non-empty-list<string> $arguments
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function runGit(array $arguments)
    {
        list($rc, $output) = $this->fs->run(Command::create('git', $arguments)->withOutput(Command::OUTPUT_CAPTURE_ALL));
        if ($rc !== 0) {
            throw new Exception\IO(trim('git ' . implode(' ', $arguments) . " failed with exit code {$rc}\n{$output}"));
        }
    }
}
