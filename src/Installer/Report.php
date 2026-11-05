<?php

namespace IPE\Installer;

/**
 * What has been installed, and how.
 */
final class Report implements \JsonSerializable
{
    /**
     * @var \IPE\Installer\InstalledDependencies
     */
    private $systemPackages;

    /**
     * @var list<\IPE\Installer\Report\CompiledLibrary>
     */
    private $compiledLibraries;

    /**
     * @var list<\IPE\Installer\Report\InstalledExtension>
     */
    private $extensions;

    /**
     * @var list<\IPE\Extension\Skipped>
     */
    private $skippedExtensions;

    /**
     * @param \IPE\Installer\InstalledDependencies|null $systemPackages
     * @param list<\IPE\Installer\Report\CompiledLibrary> $compiledLibraries
     * @param list<\IPE\Installer\Report\InstalledExtension> $extensions
     * @param list<\IPE\Extension\Skipped> $skippedExtensions
     */
    public function __construct($systemPackages = null, array $compiledLibraries = [], array $extensions = [], array $skippedExtensions = [])
    {
        $this->systemPackages = $systemPackages === null ? new InstalledDependencies() : $systemPackages;
        $this->compiledLibraries = $compiledLibraries;
        $this->extensions = $extensions;
        $this->skippedExtensions = $skippedExtensions;
    }

    /**
     * @return \IPE\Installer\InstalledDependencies
     */
    public function getSystemPackages()
    {
        return $this->systemPackages;
    }

    /**
     * @return list<\IPE\Installer\Report\CompiledLibrary>
     */
    public function getCompiledLibraries()
    {
        return $this->compiledLibraries;
    }

    /**
     * @return list<\IPE\Installer\Report\InstalledExtension>
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * @return list<\IPE\Extension\Skipped>
     */
    public function getSkippedExtensions()
    {
        return $this->skippedExtensions;
    }

    /**
     * {@inheritdoc}
     *
     * @see \JsonSerializable::jsonSerialize()
     *
     * @return array{extensions: list<\IPE\Installer\Report\InstalledExtension>, skippedExtensions: list<\IPE\Extension\Skipped>, compiledLibraries: list<\IPE\Installer\Report\CompiledLibrary>, systemPackages: array{persistent: list<non-empty-string>, volatile: list<non-empty-string>, previouslyInstalled: list<non-empty-string>}}
     */
    #[\ReturnTypeWillChange()]
    public function jsonSerialize()
    {
        return [
            'extensions' => $this->extensions,
            'skippedExtensions' => $this->skippedExtensions,
            'compiledLibraries' => $this->compiledLibraries,
            'systemPackages' => [
                'persistent' => $this->systemPackages->getPersistent(),
                'volatile' => $this->systemPackages->getVolatile(),
                'previouslyInstalled' => $this->systemPackages->getPreviouslyInstalled(),
            ],
        ];
    }
}
