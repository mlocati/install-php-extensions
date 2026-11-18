<?php

namespace IPE\GitHosts;

use IPE\GitHost;

final class GitLab implements GitHost
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'gl';

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getHandle()
     */
    public static function getHandle()
    {
        return self::HANDLE;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getName()
     */
    public function getName()
    {
        return 'GitLab';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getRepositoryExample()
     */
    public function getRepositoryExample()
    {
        return 'group/project';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::isValidRepository()
     */
    public function isValidRepository($repository)
    {
        // The projects may be in subgroups (group/subgroup/project)
        return (bool) preg_match('#^[A-Za-z0-9_][A-Za-z0-9_.\-]*(/[A-Za-z0-9_][A-Za-z0-9_.\-]*)+$#D', $repository);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getArchiveURL()
     */
    public function getArchiveURL($repository, $ref)
    {
        // Unlike the web pages, the API accepts the refs/tags/... and refs/heads/... references too
        return 'https://gitlab.com/api/v4/projects/' . rawurlencode($repository) . '/repository/archive.tar.gz?sha=' . rawurlencode($ref);
    }
}
