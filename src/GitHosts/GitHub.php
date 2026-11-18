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
}
