<?php

namespace IPE\GitHosts;

use IPE\GitHost;

final class Bitbucket implements GitHost
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'bb';

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
        return 'Bitbucket';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getRepositoryExample()
     */
    public function getRepositoryExample()
    {
        return 'workspace/repository';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::isValidRepository()
     */
    public function isValidRepository($repository)
    {
        return (bool) preg_match('#^[A-Za-z0-9_][A-Za-z0-9_.\-]*/[A-Za-z0-9_][A-Za-z0-9_.\-]*$#D', $repository);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getArchiveURL()
     */
    public function getArchiveURL($repository, $ref)
    {
        return "https://bitbucket.org/{$repository}/get/{$ref}.tar.gz";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getCloneURL()
     */
    public function getCloneURL($repository)
    {
        return "https://bitbucket.org/{$repository}.git";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::parseArchiveURL()
     */
    public function parseArchiveURL($url)
    {
        if (preg_match('_^https://bitbucket\.org/(?<repository>[^/]+/[^/]+)/get/(?<ref>.+?)\.(?:zip|tar\.gz|tar\.bz2)$_D', $url, $match) && $this->isValidRepository($match['repository'])) {
            return [$match['repository'], $match['ref']];
        }

        return null;
    }
}
