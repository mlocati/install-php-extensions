<?php

namespace IPE\Installer;

/**
 * What DependencyInstaller::install() did, so that DependencyInstaller::cleanup() can undo the volatile part (also returned by ExtensionsInstaller::install()).
 */
final class InstalledDependencies
{
    /**
     * @var list<non-empty-string>
     */
    private $persistent;

    /**
     * @var list<non-empty-string>
     */
    private $volatile;

    /**
     * @var list<non-empty-string>
     */
    private $previouslyInstalled;

    /**
     * @param list<non-empty-string> $persistent the newly installed packages to be kept
     * @param list<non-empty-string> $volatile the newly installed packages to be removed after the compilation
     * @param list<non-empty-string> $previouslyInstalled the packages that were installed before (to be restored after removing the volatile ones)
     */
    public function __construct(array $persistent = [], array $volatile = [], array $previouslyInstalled = [])
    {
        $this->persistent = $persistent;
        $this->volatile = $volatile;
        $this->previouslyInstalled = $previouslyInstalled;
    }

    /**
     * @return list<non-empty-string>
     */
    public function getPersistent()
    {
        return $this->persistent;
    }

    /**
     * @return list<non-empty-string>
     */
    public function getVolatile()
    {
        return $this->volatile;
    }

    /**
     * @return list<non-empty-string>
     */
    public function getPreviouslyInstalled()
    {
        return $this->previouslyInstalled;
    }
}
