<?php

namespace IPE\GitHosts;

use IPE\GitHost;

final class GitHub implements GitHost
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'gh';

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
        return 'GitHub';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getRepositoryExample()
     */
    public function getRepositoryExample()
    {
        return 'organization/repository';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::isValidRepository()
     */
    public function isValidRepository($repository)
    {
        return (bool) preg_match('#^[A-Za-z0-9\-]{1,39}/[A-Za-z0-9.\-_]{1,100}$#D', $repository);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getArchiveURL()
     */
    public function getArchiveURL($repository, $ref)
    {
        return "https://codeload.github.com/{$repository}/tar.gz/{$ref}";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getCloneURL()
     */
    public function getCloneURL($repository)
    {
        return "https://github.com/{$repository}.git";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::getRawFileURL()
     */
    public function getRawFileURL($repository, $ref, $path)
    {
        return "https://raw.githubusercontent.com/{$repository}/" . self::encodePath($ref === '' ? 'HEAD' : $ref) . '/' . self::encodePath($path);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private static function encodePath($path)
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\GitHost::parseArchiveURL()
     */
    public function parseArchiveURL($url)
    {
        $patterns = [
            '_^https://github\.com/(?<repository>[^/]+/[^/]+)/archive/(?<ref>.+?)\.(?:zip|tar\.gz)$_D',
            '_^https://codeload\.github\.com/(?<repository>[^/]+/[^/]+)/(?:legacy\.)?(?:zip|tar\.gz)/(?<ref>.+)$_D',
            '_^https://api\.github\.com/repos/(?<repository>[^/]+/[^/]+)/(?:zip|tar)ball/(?<ref>.+)$_D',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $match) && $this->isValidRepository($match['repository'])) {
                return [$match['repository'], $match['ref']];
            }
        }

        return null;
    }
}
