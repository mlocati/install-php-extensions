<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency\CompiledLibrary;
use IPE\SystemPackager\Packages;

/**
 * Debian, Ubuntu and the RHEL-like systems don't provide the Cassandra C++ driver.
 */
final class libcassandra extends CompiledLibrary
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-cassandra';

    /**
     * @public
     * @var string
     */
    const VERSION = '2.17.1';

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
        if ($this->os instanceof OS\Alpine) {
            return new Packages(
                ['cassandra-cpp-driver', 'gmp'],
                ['cassandra-cpp-driver-dev', 'gmp-dev', 'bash', 'cmake']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::JESSIE) {
                // libuv1 is missing
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['libstdc++6', '^libuv1(t64)?$', '^libgmp10(t64)?$'],
                ['cmake', 'g++', 'libuv1-dev', 'zlib1g-dev', 'libgmp-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::TRUSTY) {
                // libuv1 is missing
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['libstdc++6', '^libuv1(t64)?$', '^libgmp10(t64)?$'],
                ['cmake', 'g++', 'libuv1-dev', 'zlib1g-dev', 'libgmp-dev']
            );
        }
        if ($this->os instanceof OS\Fedora && $this->os->getNumericVersion() >= 41 && $this->os->getNumericVersion() <= 44) {
            // openssl-devel doesn't include openssl/engine.h
            return new Packages(
                ['libstdc++', 'libuv', 'gmp'],
                ['cmake', 'gcc-c++', 'libuv-devel', 'zlib-devel', 'gmp-devel', 'openssl-devel-engine']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libstdc++', 'libuv', 'gmp'],
                ['cmake', 'gcc-c++', 'libuv-devel', 'zlib-devel', 'gmp-devel']
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
        return ($this->os instanceof OS\Debian || $this->os instanceof OS\Ubuntu || $this->os instanceof OS\Posix\RHELLike) && parent::needsCompilation();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::isInstalled()
     */
    protected function isInstalled()
    {
        return $this->os->getFilesystem()->isFile('/usr/local/lib/libcassandra.so');
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::getBuildDependencies()
     */
    protected function getBuildDependencies()
    {
        return [libopenssl::HANDLE];
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
        return 'https://github.com/apache/cassandra-cpp-driver/archive/refs/tags/' . self::VERSION . '.tar.gz';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency\CompiledLibrary::build()
     */
    protected function build($sourceDirectory)
    {
        $arguments = [
            '-B', 'build',
            '-S', '.',
            '-DCMAKE_BUILD_TYPE=Release',
            '-DCMAKE_INSTALL_LIBDIR=lib',
            '-DCMAKE_POLICY_VERSION_MINIMUM=3.5',
            '-DCMAKE_POSITION_INDEPENDENT_CODE=ON',
            '-DCASS_BUILD_SHARED=ON',
            '-DCASS_BUILD_STATIC=OFF',
            '-DCASS_BUILD_EXAMPLES=OFF',
            '-DCASS_BUILD_TESTS=OFF',
            '-DCASS_USE_STD_ATOMIC=ON',
            '-DCASS_USE_TIMERFD=ON',
            '-DCASS_USE_LIBSSH2=OFF',
            '-DCASS_USE_ZLIB=ON',
        ];
        if ($this->getOpenSSLMajorVersion() >= 4) {
            // The X509 functions of OpenSSL 4 return const pointers, which the driver assigns to non-const variables.
            // The driver appends -Werror to CMAKE_CXX_FLAGS: CMAKE_CXX_FLAGS_RELEASE comes after it
            $arguments[] = '-DCMAKE_CXX_FLAGS_RELEASE=-O3 -DNDEBUG -fpermissive -Wno-error';
        }
        $this->runBuildStep('cmake', $arguments, $sourceDirectory);
        $this->runBuildStep('cmake', ['--build', 'build', '--parallel', (string) $this->getParallelJobs()], $sourceDirectory);
        $this->runBuildStep('cmake', ['--build', 'build', '--target', 'install'], $sourceDirectory);
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return int 0 if the OpenSSL headers are missing
     */
    private function getOpenSSLMajorVersion()
    {
        $fs = $this->os->getFilesystem();
        $file = '/usr/include/openssl/opensslv.h';
        $matches = null;

        return $fs->isFile($file) && preg_match('/^#\s*define\s+OPENSSL_VERSION_MAJOR\s+(?<major>\d+)/m', $fs->readFile($file), $matches) ? (int) $matches['major'] : 0;
    }
}
