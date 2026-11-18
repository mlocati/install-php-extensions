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
}
