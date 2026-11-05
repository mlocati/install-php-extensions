<?php

namespace IPE\ComposerRepository;

use IPE\ComposerRepository;
use IPE\Exception;

/**
 * The Composer repositories where the Composer packages are looked for.
 */
class Registry
{
    /**
     * @public
     * @var string
     */
    const PACKAGIST_URL = 'https://repo.packagist.org';

    /**
     * Lower than the default priority, so that the other repositories are searched first.
     *
     * @public
     * @var int
     */
    const PACKAGIST_PRIORITY = -1000;

    /**
     * @var array<non-empty-string, \IPE\ComposerRepository> the keys are the URLs of the repositories, in order of registration
     */
    private $repositories = [];

    /**
     * @throws \IPE\Exception\InvalidArgument if packagist.org is already registered
     *
     * @return $this
     */
    public function registerDefaultRepositories()
    {
        // Knowing the metadata-url saves the request of packages.json
        return $this->register(new ComposerRepository(self::PACKAGIST_URL, self::PACKAGIST_PRIORITY, self::PACKAGIST_URL . '/p2/%package%.json'));
    }

    /**
     * @throws \IPE\Exception\InvalidArgument if there's already a registered repository with the URL of $repository
     *
     * @return $this
     */
    public function register(ComposerRepository $repository)
    {
        $url = $repository->getURL();
        if (isset($this->repositories[$url])) {
            throw new Exception\InvalidArgument("The Composer repository {$url} is already registered");
        }
        $this->repositories[$url] = $repository;

        return $this;
    }

    /**
     * @param non-empty-string $url
     *
     * @return $this
     */
    public function unregister($url)
    {
        unset($this->repositories[ComposerRepository::normalizeURL($url)]);

        return $this;
    }

    /**
     * @return list<\IPE\ComposerRepository> sorted by descending priority (and by registration order for the same priority)
     */
    public function getRepositories()
    {
        $repositories = array_values($this->repositories);
        $order = array_flip(array_keys($this->repositories));
        usort($repositories, static function (ComposerRepository $a, ComposerRepository $b) use ($order) {
            return $b->getPriority() - $a->getPriority() ?: $order[$a->getURL()] - $order[$b->getURL()];
        });

        return $repositories;
    }
}
