<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency\CompiledLibrary;
use IPE\SystemPackager\Packages;

/**
 * Enchant 1: recent operating systems don't provide it anymore, so we compile it.
 */
final class libenchant extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-enchant';

    /**
     * @public
     * @var string
     */
    const VERSION = '1.6.1';

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getHandle()
     */
    public static function getHandle()
    {
        return self::HANDLE;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        $osProvidesEnchant1 = !libenchant2::isProvidedByOS($this->os);
        if ($this->os instanceof OS\Alpine) {
            if ($osProvidesEnchant1) {
                return new Packages(
                    ['enchant'],
                    ['enchant-dev']
                );
            }
            if ($this->isInstalled()) {
                return new Packages();
            }

            return new Packages(
                ['glib', 'aspell-libs', 'libhunspell'],
                ['glib-dev', 'aspell-dev', 'hunspell-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if ($osProvidesEnchant1) {
                return new Packages(
                    ['libenchant1c2a'],
                    ['libenchant-dev']
                );
            }
            if ($this->isInstalled()) {
                return new Packages();
            }

            return new Packages(
                ['aspell-en', 'libhunspell-1.7-0'],
                ['libglib2.0-dev', 'libaspell-dev', 'libhunspell-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::needsCompilation()
     */
    protected function needsCompilation()
    {
        return libenchant2::isProvidedByOS($this->os) && parent::needsCompilation();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        $fs = $this->os->getFilesystem();

        return $fs->isFile('/usr/lib/libenchant.so') || $fs->isFile('/usr/local/lib/libenchant.so');
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getBuildDependencies()
     */
    protected function getBuildDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getCompiledVersion()
     */
    public function getCompiledVersion()
    {
        return self::VERSION;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getSourceURL()
     */
    protected function getSourceURL()
    {
        return 'https://github.com/rrthomas/enchant/releases/download/enchant-' . str_replace('.', '-', self::VERSION) . '/enchant-' . self::VERSION . '.tar.gz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $this->runBuildStep('./configure', [], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs(), 'install'], $sourceDirectory);
    }
}
