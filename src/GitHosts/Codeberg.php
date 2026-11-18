<?php

namespace IPE\GitHosts;

use IPE\GitHost;

final class Codeberg implements GitHost
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'cb';

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
        return 'Codeberg';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getRepositoryExample()
     */
    public function getRepositoryExample()
    {
        return 'owner/repository';
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
        return "https://codeberg.org/{$repository}/archive/{$ref}.tar.gz";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getCloneURL()
     */
    public function getCloneURL($repository)
    {
        return "https://codeberg.org/{$repository}.git";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getRawFileURL()
     */
    public function getRawFileURL($repository, $ref, $path)
    {
        return "https://codeberg.org/api/v1/repos/{$repository}/raw/" . implode('/', array_map('rawurlencode', explode('/', $path))) . ($ref === '' ? '' : '?ref=' . rawurlencode($ref));
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::parseArchiveURL()
     */
    public function parseArchiveURL($url)
    {
        if (preg_match('_^https://codeberg\.org/(?<repository>[^/]+/[^/]+)/archive/(?<ref>.+?)\.(?:zip|tar\.gz)$_D', $url, $match) && $this->isValidRepository($match['repository'])) {
            return [$match['repository'], $match['ref']];
        }

        return null;
    }
}
