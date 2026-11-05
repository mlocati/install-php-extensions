<?php

namespace IPE\SystemDependencies;

use IPE\Installer\BuildOptions;
use IPE\Installer\BuildTools;
use IPE\Installer\State;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependency\CompiledLibrary;
use IPE\SystemPackager\Packages;

/**
 * Recent operating systems provide it, on the other ones we need to compile it (together with libaom and libyuv).
 */
class libavif extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-avif';

    /**
     * @public
     * @var string
     */
    const VERSION = '1.3.0';

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
     * Can we have libavif without compiling anything?
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    public static function isAvailableWithoutCompiling(PHPInfo $php, OS $os)
    {
        return self::isProvidedByOS($php, $os) || BuildTools::hasPkgConfigModuleFor($os->getFilesystem(), self::getPkgConfigModule($php));
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        if (!self::isProvidedByOS($this->php, $this->os)) {
            return parent::getSystemPackages($state);
        }
        if ($this->os instanceof OS\Alpine) {
            return new Packages(
                ['libavif', 'aom-libs', 'libdav1d'],
                ['libavif-dev', 'aom-dev', 'dav1d-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['^libavif[0-9]+$', '^libaom[0-9]+$', '^libdav1d[0-9]+$'],
                ['libavif-dev', 'libaom-dev', 'libdav1d-dev']
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
        return !self::isProvidedByOS($this->php, $this->os) && parent::needsCompilation();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        return $this->buildTools->hasPkgConfigModule(self::getPkgConfigModule($this->php));
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getBuildDependencies()
     */
    protected function getBuildDependencies()
    {
        return [libyuv::HANDLE, libaom::HANDLE, cmake::HANDLE];
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
        return 'https://codeload.github.com/AOMediaCodec/libavif/tar.gz/refs/tags/v' . self::VERSION;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $buildDirectory = $this->createBuildDirectory($sourceDirectory);
        $this->runBuildStep('cmake', [
            '..',
            '-DCMAKE_BUILD_TYPE=' . ($this->buildOptions->getType() === BuildOptions::TYPE_DEBUG ? 'Debug' : 'Release'),
            '-DBUILD_SHARED_LIBS=ON',
            '-DAVIF_CODEC_AOM=SYSTEM',
            '-DCMAKE_INSTALL_LIBDIR:PATH=lib',
        ], $buildDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs(), 'install'], $buildDirectory);
    }

    /**
     * A recent enough version number is not enough: the libavif of Debian 11 and Alpine 3.14 can be compiled against, but it fails with "No codec available" when encoding.
     *
     * @return bool
     */
    private static function isProvidedByOS(PHPInfo $php, OS $os)
    {
        if ($os instanceof OS\Alpine) {
            return $os->getNumericVersion() >= (self::requiresVersion1($php) ? 319 : 315);
        }
        if ($os instanceof OS\Debian) {
            return $os->getNumericVersion() >= (self::requiresVersion1($php) ? OS\Debian::VERSION_TRIXIE : OS\Debian::VERSION_BOOKWORM);
        }

        return false;
    }

    /**
     * @return non-empty-string
     */
    private static function getPkgConfigModule(PHPInfo $php)
    {
        return 'libavif >= ' . (self::requiresVersion1($php) ? '1.0' : '0.8.2');
    }

    /**
     * @return bool
     */
    private static function requiresVersion1(PHPInfo $php)
    {
        return $php->getMajorMinorVersion() >= 806;
    }
}
