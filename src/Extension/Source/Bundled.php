<?php

namespace IPE\Extension\Source;

use Composer\Semver\Semver;
use IPE\Exception;
use IPE\Extension\BundledInPHPSource;
use IPE\Installer\PHPSourceProvider;
use IPE\Installer\State;
use IPE\PHPInfo;

/**
 * Extensions bundled in the PHP source code.
 */
final class Bundled
{
    /**
     * @var \IPE\Installer\PHPSourceProvider
     */
    private $phpSourceProvider;

    public function __construct(PHPSourceProvider $phpSourceProvider)
    {
        $this->phpSourceProvider = $phpSourceProvider;
    }

    /**
     * Is the extension bundled in the PHP version in use?
     *
     * @return bool
     */
    public function isAvailable(BundledInPHPSource $extension, PHPInfo $php)
    {
        return Semver::satisfies($php->getBaseVersion(), $extension::getBundledPHPVersions());
    }

    /**
     * Provide the directory of the extension inside the PHP source code.
     *
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     *
     * @return non-empty-string
     */
    public function fetch(BundledInPHPSource $extension, State $state)
    {
        $fs = $state->getFilesystem();
        $phpSourceDir = $this->phpSourceProvider->providePHPSourceDirectory($state);
        $sourceDir = $phpSourceDir . $fs->getDirectorySeparator() . 'ext' . $fs->getDirectorySeparator() . $extension::getPHPSourceExtDirectoryName();
        if (!$fs->isDirectory($sourceDir)) {
            throw new Exception\IO("The PHP source code doesn't contain the directory of the extension: {$sourceDir}");
        }

        return $sourceDir;
    }
}
