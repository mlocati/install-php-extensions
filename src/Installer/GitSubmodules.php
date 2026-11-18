<?php

namespace IPE\Installer;

use IPE\Exception;
use IPE\Extension\GitSource;
use IPE\Extension\Resolved;
use IPE\Filesystem;
use IPE\Filesystem\Command;

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
     * @var \IPE\Installer\BuildOptions
     */
    private $buildOptions;

    /**
     * @var \IPE\Logger
     */
    private $logger;

    public function __construct(Filesystem $fs, BuildOptions $buildOptions, \IPE\Logger $logger)
    {
        $this->fs = $fs;
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
            $root = $gitSource->getRoot();
            if ($this->findMissing($root, $this->readSubmodulePaths($root, false)) !== [] && ($gitSource->getRemoteURL() !== '' || $this->isWorkingTree($root))) {
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
        $root = $gitSource->getRoot();
        $paths = $this->readSubmodulePaths($root, true);
        if ($paths === []) {
            $this->logger->debug("No git submodules are declared in {$root}");

            return;
        }
        $missing = $this->findMissing($root, $paths);
        if ($missing === []) {
            $this->logger->debug("The git submodules of {$resolvedExtension} are already present: " . implode(', ', $paths));

            return;
        }
        $description = "the git submodules of {$resolvedExtension} (" . implode(', ', $missing) . ')';
        try {
            if ($gitSource->getRemoteURL() !== '') {
                $this->logger->info("Fetching {$description} from {$gitSource->getRemoteURL()}");
                $this->fetchFromRepository($gitSource, $root, $missing, $state);
            } elseif ($this->isWorkingTree($root)) {
                $this->logger->info("Fetching {$description}");
                // The directory may be owned by another user (for example, when it's mounted in a container).
                // Only the missing submodules, so that we don't change the commits of the other ones.
                $this->runGit(array_merge(['-c', "safe.directory={$root}", '-C', $root, 'submodule', 'update', '--init', '--recursive', '--'], $missing));
            } else {
                $this->logger->warning("Unable to fetch {$description}: {$root} is not a git working tree and its repository is unknown");
            }
        } catch (Exception\IO $x) {
            $this->logger->warning("Unable to fetch {$description}: {$x->getMessage()}");
        }
    }

    /**
     * @param non-empty-string $root
     * @param list<non-empty-string> $paths
     *
     * @throws \IPE\Exception\IO
     *
     * @return list<non-empty-string> the paths of the submodules that are missing
     */
    private function findMissing($root, array $paths)
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
        $matches = null;
        preg_match_all('/^\s*path\s*=\s*(.+?)\s*$/m', $this->fs->readFile("{$root}/.gitmodules"), $matches);
        $result = [];
        foreach ($matches[1] as $declaredPath) {
            $path = rtrim($declaredPath, '/');
            if (self::isValidPath($path)) {
                $result[] = $path;
            } elseif ($warnInvalid) {
                $this->logger->warning("Ignoring the git submodule path '{$declaredPath}' of {$root}/.gitmodules: it must be a relative path inside that directory");
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
            return strpos($this->fs->readFile($git), 'gitdir:') === 0;
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
     * @param non-empty-string $root
     * @param non-empty-list<non-empty-string> $missing
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function fetchFromRepository(GitSource $gitSource, $root, array $missing, State $state)
    {
        $clone = $state->getVolatileDir()->createSubdir();
        $url = $gitSource->getRemoteURL();
        $reference = $gitSource->getRemoteReference();
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
