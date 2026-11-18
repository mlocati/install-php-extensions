<?php

namespace IPE\GitHost;

use IPE\Exception;
use IPE\GitHost;
use IPE\GitHosts;

final class Registry
{
    /**
     * @var array<non-empty-string, \IPE\GitHost> the keys are the handles
     */
    private $hosts = [];

    /**
     * @throws \IPE\Exception\InvalidArgument if a default host is already registered
     *
     * @return $this
     */
    public function registerDefaultHosts()
    {
        return $this
            ->register(new GitHosts\GitHub())
            ->register(new GitHosts\GitLab())
            ->register(new GitHosts\Codeberg())
            ->register(new GitHosts\Bitbucket())
        ;
    }

    /**
     * @throws \IPE\Exception\InvalidArgument if another host uses the same handle
     *
     * @return $this
     */
    public function register(GitHost $host)
    {
        $handle = $host::getHandle();
        if (isset($this->hosts[$handle])) {
            throw new Exception\InvalidArgument("The handle '{$handle}' of {$host->getName()} is already used by {$this->hosts[$handle]->getName()}");
        }
        $this->hosts[$handle] = $host;

        return $this;
    }

    /**
     * @return list<\IPE\GitHost>
     */
    public function getHosts()
    {
        return array_values($this->hosts);
    }

    /**
     * @param string $handle
     *
     * @return \IPE\GitHost|null
     */
    public function getByHandle($handle)
    {
        return isset($this->hosts[$handle]) ? $this->hosts[$handle] : null;
    }
}
