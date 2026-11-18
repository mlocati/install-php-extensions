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

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getCloneURL()
     */
    public function getCloneURL($repository)
    {
        return "https://gitlab.com/{$repository}.git";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getRawFileURL()
     */
    public function getRawFileURL($repository, $ref, $path)
    {
        // The API, since the web URLs don't support the refs/... references
        return 'https://gitlab.com/api/v4/projects/' . rawurlencode($repository) . '/repository/files/' . rawurlencode($path) . '/raw' . ($ref === '' ? '' : '?ref=' . rawurlencode($ref));
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::parseArchiveURL()
     */
    public function parseArchiveURL($url)
    {
        if (preg_match('_^https://gitlab\.com/api/v4/projects/(?<repository>[^/?]+)/repository/archive(?:\.[a-z0-9.]+)?(?:\?(?<query>.*))?$_D', $url, $match)) {
            $repository = rawurldecode($match['repository']);
            $query = [];
            if (isset($match['query'])) {
                parse_str($match['query'], $query);
            }
            $ref = isset($query['sha']) && is_string($query['sha']) ? $query['sha'] : '';
        } elseif (preg_match('_^https://gitlab\.com/(?<repository>.+?)/-/archive/(?<ref>.+)/[^/]+\.(?:zip|tar\.gz|tar\.bz2|tar)(?:\?.*)?$_D', $url, $match)) {
            $repository = $match['repository'];
            $ref = $match['ref'];
        } else {
            return null;
        }

        return $this->isValidRepository($repository) ? [$repository, $ref] : null;
    }
}
