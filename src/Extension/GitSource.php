<?php

namespace IPE\Extension;

use IPE\GitHost;

/**
 * Where the source code of an extension comes from, so that we can fetch the git submodules that the archives don't include.
 */
final class GitSource
{
    /**
     * @var non-empty-string
     */
    private $root;

    /**
     * @var string
     */
    private $remoteURL = '';

    /**
     * @var string
     */
    private $remoteReference = '';

    /**
     * @var string
     */
    private $gitmodulesURL = '';

    /**
     * @param non-empty-string $root the local directory, or the root of the downloaded archive (not its subdirectory with the extension)
     */
    public function __construct($root)
    {
        $this->root = $root;
    }

    /**
     * @param non-empty-string $url the URL of the git repository to be cloned
     * @param string $reference the commit, tag or branch of the source code (empty string for the default branch)
     *
     * @return self
     */
    public function withRemoteRepository($url, $reference)
    {
        $result = clone $this;
        $result->remoteURL = $url;
        $result->remoteReference = $reference;
        $result->gitmodulesURL = '';

        return $result;
    }

    /**
     * Set the remote repository of an archive downloaded from a URL: we know it only for the archives of the git hosts.
     *
     * @param string $url
     *
     * @return self
     */
    public function withArchiveURL($url, GitHost\Registry $gitHosts)
    {
        foreach ($gitHosts->getHosts() as $host) {
            $parsed = $host->parseArchiveURL($url);
            if ($parsed !== null) {
                $result = $this->withRemoteRepository($host->getCloneURL($parsed[0]), $parsed[1]);
                $result->gitmodulesURL = $host->getRawFileURL($parsed[0], $parsed[1], '.gitmodules');

                return $result;
            }
        }

        return $this;
    }

    /**
     * @return non-empty-string
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * @return string empty string if the remote repository is unknown
     */
    public function getRemoteURL()
    {
        return $this->remoteURL;
    }

    /**
     * @return string empty string for the default branch, or if the remote repository is unknown
     */
    public function getRemoteReference()
    {
        return $this->remoteReference;
    }

    /**
     * The archives may lack the .gitmodules file (when it's export-ignore in .gitattributes): we can download it without cloning the repository.
     *
     * @return string empty string if unknown
     */
    public function getGitmodulesURL()
    {
        return $this->gitmodulesURL;
    }
}
