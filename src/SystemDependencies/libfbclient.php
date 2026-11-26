<?php

namespace IPE\SystemDependencies;

use IPE\Exception;
use IPE\Filesystem\Command;
use IPE\Installer\State;
use IPE\OS;
use IPE\Service\Regex;
use IPE\SystemDependency\CompiledLibrary;
use IPE\SystemPackager\Homebrew;
use IPE\SystemPackager\Packages;

/**
 * Alpine doesn't provide the Firebird client library: we need to compile it (the extensions of PHP < 8.4 require version 2.5).
 */
final class libfbclient extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-fbclient';

    /**
     * @public
     * @var string
     */
    const VERSION = '5.0.4.1812-0';

    /**
     * @public
     * @var string
     */
    const VERSION_OLD = '2.5.9.27139-0';

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
     * Get the name of the formula of the Homebrew extensions tap that provides the library.
     *
     * @param int $phpMajorMinorVersion
     *
     * @return non-empty-string
     */
    public static function getHomebrewFormula($phpMajorMinorVersion)
    {
        // The tap builds pdo_firebird for PHP < 8.4 with Firebird 3
        return $phpMajorMinorVersion < 804 ? 'firebird-client@3' : 'firebird-client';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        if ($this->os instanceof OS\Alpine) {
            if ($this->isInstalled()) {
                return new Packages();
            }
            if ($this->usesOldVersion()) {
                return new Packages(
                    [],
                    ['icu-dev', 'ncurses-dev']
                );
            }

            return new Packages(
                ['libstdc++'],
                ['icu-dev', 'ncurses-dev', 'zlib-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                ['libfbclient2'],
                ['firebird-dev', 'libib-util']
            );
        }
        if ($this->os instanceof OS\AmazonLinux) {
            throw $this->createNotProvidedException();
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libfbclient2'],
                ['firebird-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            // homebrew-core doesn't provide Firebird
            try {
                $formula = $this->os->getSystemPackager()->getExtensionsTapFormula(self::getHomebrewFormula($this->php->getMajorMinorVersion()));
            } catch (Exception\SystemPackager $x) {
                throw new Exception\UnsupportedOS($x->getMessage());
            }
            if ($formula === '') {
                throw new Exception\UnsupportedOS('The Firebird client library is only available in the ' . Homebrew::EXTENSIONS_TAP . ' tap of Homebrew (which requires the ' . Homebrew::PHP_TAP . ' tap)');
            }

            return new Packages(
                [$formula]
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
        return $this->os instanceof OS\Alpine && parent::needsCompilation();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        $fs = $this->os->getFilesystem();

        return $fs->isFile('/usr/lib/libfbclient.so') && $fs->isFile('/usr/include/ibase.h');
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getBuildDependencies()
     */
    protected function getBuildDependencies()
    {
        return $this->usesOldVersion() ? [] : [libtommath::HANDLE, libtomcrypt::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getCompiledVersion()
     */
    public function getCompiledVersion()
    {
        return $this->usesOldVersion() ? self::VERSION_OLD : self::VERSION;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getSourceURL()
     */
    protected function getSourceURL()
    {
        if ($this->usesOldVersion()) {
            return 'https://github.com/FirebirdSQL/firebird/releases/download/R2_5_9/Firebird-' . self::VERSION_OLD . '.tar.bz2';
        }
        $tag = substr(self::VERSION, 0, (int) strrpos(self::VERSION, '.'));

        return "https://github.com/FirebirdSQL/firebird/releases/download/v{$tag}/Firebird-" . self::VERSION . '-source.tar.xz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        if ($this->usesOldVersion()) {
            $this->buildOldVersion($sourceDirectory);
        } else {
            $this->buildNewVersion($sourceDirectory);
        }
    }

    /**
     * @param non-empty-string $sourceDirectory
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function buildNewVersion($sourceDirectory)
    {
        $fs = $this->os->getFilesystem();
        // Turn off profiling on arm64, otherwise the build fails with "undefined reference to `_mcount'"
        $prefixFile = "{$sourceDirectory}/builds/posix/prefix.linux_arm64";
        if ($fs->isFile($prefixFile)) {
            $fs->writeFile($prefixFile, (string) preg_replace(['/ -p /', '/ -p$/m', '/^-p /m'], ' ', $fs->readFile($prefixFile)));
        }
        $this->runBuildStep('./configure', ['--enable-client-only'], $sourceDirectory);
        $this->runBuildStep($this->buildTools->getMake(), ['-j' . $this->getParallelJobs()], $sourceDirectory);
        $this->runBuildStep('sh', ['-c', 'cp -Rd gen/Release/firebird/include/* /usr/include && cp -Rd gen/Release/firebird/lib/* /usr/lib'], $sourceDirectory);
    }

    /**
     * @param non-empty-string $sourceDirectory
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function buildOldVersion($sourceDirectory)
    {
        $fs = $this->os->getFilesystem();
        // musl doesn't have PTHREAD_RWLOCK_PREFER_WRITER_NONRECURSIVE_NP (fixed in later releases of Firebird)
        $rwlockFile = "{$sourceDirectory}/src/common/classes/rwlock.h";
        $lines = explode("\n", $fs->readFile($rwlockFile));
        $lines[193] = '#if 0';
        $fs->writeFile($rwlockFile, implode("\n", $lines));
        // Firebird 2.5 doesn't support recent ICU versions
        $this->runBuildStep('./configure', $this->os->getNumericVersion() >= 322 ? [] : ['--with-system-icu'], $sourceDirectory);
        // Don't enable parallel builds: Firebird forces -j1
        $this->runBuildStep($this->buildTools->getMake(), ['-s', 'btyacc_binary', 'gpre_boot', 'libfbstatic', 'libfbclient'], $sourceDirectory);
        $fs->copyFile("{$sourceDirectory}/gen/firebird/lib/libfbclient.so", '/usr/lib/libfbclient.so');
        $fs->run(Command::create('ln', ['-sf', '/usr/lib/libfbclient.so', '/usr/lib/libfbclient.so.2']));
        $fs->writeFile('/usr/include/ibase.h', $this->buildPublicHeader($sourceDirectory));
    }

    /**
     * The ibase.h file of the source code includes other private files: merge them, as the Firebird build does when creating the installer.
     *
     * @param non-empty-string $sourceDirectory
     *
     * @throws \IPE\Exception\IO
     *
     * @return string
     */
    private function buildPublicHeader($sourceDirectory)
    {
        $fs = $this->os->getFilesystem();

        return Regex::replaceCallback(
            '/^#include "(?<file>[^"]+)"[ \t]*$/m',
            static function (array $matches) use ($fs, $sourceDirectory) {
                foreach (['src/jrd', 'src/include', 'src/include/gen'] as $directory) {
                    $path = "{$sourceDirectory}/{$directory}/{$matches['file']}";
                    if ($fs->isFile($path)) {
                        return $fs->readFile($path);
                    }
                }

                return $matches[0];
            },
            $fs->readFile("{$sourceDirectory}/src/jrd/ibase.h")
        );
    }

    /**
     * @return bool
     */
    private function usesOldVersion()
    {
        return $this->php->getMajorMinorVersion() < 804;
    }
}
