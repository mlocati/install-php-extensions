<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libxml2 extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-xml2';

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
                [],
                ['libxml2-dev']
            );
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return new Packages(
                [],
                ['libxml2-dev']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['libxml2'],
                ['libxml2-devel']
            );
        }
        if ($this->os instanceof OS\MacOS) {
            // The PHP of Homebrew uses the libxml2 of the SDK (see getBuildEnvironment())
            return new Packages();
        }

        throw $this->createUnknownPackagesException();
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getBuildEnvironment()
     */
    public function getBuildEnvironment()
    {
        $result = [];
        // The SDK has no .pc file for libxml2: the extensions must use it, since they share its data with the libxml extension of PHP
        if ($this->os instanceof OS\MacOS && getenv('LIBXML_CFLAGS') === false && getenv('LIBXML_LIBS') === false) {
            $sdkPath = $this->os->getSDKPath();
            if ($sdkPath !== '') {
                $result['LIBXML_CFLAGS'] = "-I{$sdkPath}/usr/include/libxml2";
                $result['LIBXML_LIBS'] = '-lxml2';
            }
        }

        return $result;
    }
}
