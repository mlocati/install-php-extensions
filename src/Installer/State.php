<?php

namespace IPE\Installer;

use IPE\Extension\Skipped;
use IPE\Filesystem;
use IPE\PHPInfo;

class State
{
    /**
     * @var \IPE\PHPInfo
     */
    private $php;

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var \IPE\Filesystem\VolatileDirectory|null
     */
    private $volatileDir;

    /**
     * @var array<string, array<string, string|true>>
     */
    private $systemDependencyOptions = [];

    /**
     * @var list<\IPE\Extension\Skipped>
     */
    private $skippedExtensions = [];

    /**
     * @var \IPE\Installer\State\ShutdownCallbacks
     */
    private $shutdownCallbacks;

    public function __construct(PHPInfo $php, Filesystem $fs)
    {
        $this->php = $php;
        $this->fs = $fs;
        $this->volatileDir = null;
        $this->shutdownCallbacks = new State\ShutdownCallbacks();
    }

    /**
     * @param array<string, array<string, string|true>> $options the options specified by the user: the keys are the handles of the system dependencies
     *
     * @return static
     */
    public function withSystemDependencyOptions(array $options)
    {
        $clone = clone $this;
        $clone->systemDependencyOptions = $options;

        return $clone;
    }

    /**
     * @return list<string>
     */
    public function getHandlesWithSystemDependencyOptions()
    {
        return array_map('strval', array_keys($this->systemDependencyOptions));
    }

    /**
     * Get the options specified by the user for a system dependency.
     *
     * @param non-empty-string $handle
     *
     * @return array<string, string|true>
     */
    public function getSystemDependencyUserOptions($handle)
    {
        return isset($this->systemDependencyOptions[$handle]) ? $this->systemDependencyOptions[$handle] : [];
    }

    /**
     * @param list<\IPE\Extension\Skipped> $skippedExtensions
     *
     * @return $this
     */
    public function setSkippedExtensions(array $skippedExtensions)
    {
        $this->skippedExtensions = $skippedExtensions;

        return $this;
    }

    /**
     * @return $this
     */
    public function addSkippedExtension(Skipped $skippedExtension)
    {
        $this->skippedExtensions[] = $skippedExtension;

        return $this;
    }

    /**
     * Get the extensions skipped by the last resolution of the asked extensions.
     *
     * @return list<\IPE\Extension\Skipped>
     */
    public function getSkippedExtensions()
    {
        return $this->skippedExtensions;
    }

    /**
     * @return \IPE\PHPInfo
     */
    public function getPHP()
    {
        return $this->php;
    }

    /**
     * @return \IPE\Filesystem
     */
    public function getFilesystem()
    {
        return $this->fs;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Filesystem\VolatileDirectory
     */
    public function getVolatileDir()
    {
        if ($this->volatileDir === null) {
            $this->volatileDir = $this->fs->createVolatileDirectory();
        }

        return $this->volatileDir;
    }

    /**
     * Register a function to be called by shutdown(), for example to undo a temporary change.
     *
     * @param \Closure(): void $callback
     *
     * @return $this
     */
    public function registerShutdownCallback(\Closure $callback)
    {
        $this->shutdownCallbacks->add($callback);

        return $this;
    }

    /**
     * Unregister a function registered with registerShutdownCallback(), for example because it has already been called.
     *
     * @return bool false if the function wasn't registered
     */
    public function unregisterShutdownCallback(\Closure $callback)
    {
        return $this->shutdownCallbacks->remove($callback);
    }

    /**
     * Call the functions registered with registerShutdownCallback(), the last registered first: whoever creates the state should call this at the end.
     *
     * @param \IPE\Logger $logger where to report the functions that fail
     *
     * @return void
     */
    public function shutdown(\IPE\Logger $logger)
    {
        $this->shutdownCallbacks->run($logger);
    }
}
