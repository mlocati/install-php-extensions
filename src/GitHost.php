<?php

namespace IPE;

/**
 * A service hosting git repositories, that can be specified with a shortcut like gh:organization/repository@ref.
 */
interface GitHost
{
    /**
     * Get the handle used in the shortcuts (for example 'gh').
     *
     * @return non-empty-string
     */
    public static function getHandle();

    /**
     * Get the name of the service (for example 'GitHub').
     *
     * @return non-empty-string
     */
    public function getName();

    /**
     * Get an example of the repository part of the shortcuts (for example 'organization/repository').
     *
     * @return non-empty-string
     */
    public function getRepositoryExample();

    /**
     * @param string $repository the repository part of a shortcut
     *
     * @return bool
     */
    public function isValidRepository($repository);

    /**
     * Get the URL of the archive with the source code.
     *
     * @param non-empty-string $repository a valid repository
     * @param non-empty-string $ref a commit (full or abbreviated), a tag or a branch, optionally prefixed by refs/tags/ or refs/heads/
     *
     * @return non-empty-string
     */
    public function getArchiveURL($repository, $ref);

    /**
     * Get the URL to be used to clone a repository.
     *
     * @param non-empty-string $repository a valid repository
     *
     * @return non-empty-string
     */
    public function getCloneURL($repository);

    /**
     * Extract the repository and the ref from the URL of an archive with the source code.
     *
     * @param string $url
     *
     * @return array{non-empty-string, string}|null the repository and the ref (empty string for the default branch), or NULL if the URL is not an archive of this service
     */
    public function parseArchiveURL($url);
}
