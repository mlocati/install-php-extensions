<?php

namespace IPE\SystemDependencies;

use IPE\Exception;
use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libnode extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-node';

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
                ['libstdc++', 'nodejs-libs'],
                ['nodejs-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::STRETCH) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['^libnode[0-9]+$'],
                ['libnode-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::BIONIC) {
                throw $this->createNotProvidedException();
            }

            return new Packages(
                ['^libnode[0-9]+$'],
                ['libnode-dev']
            );
        }
        if ($this->os instanceof OS\Fedora && $this->os->getNumericVersion() <= 44) {
            // nodejs-libs is provided by nodejs22-libs, nodejs-devel by nodejs24-devel
            return new Packages(
                ['nodejs22-libs'],
                ['nodejs22-devel']
            );
        }
        if ($this->os instanceof OS\Posix\RHELLike\EnterpriseLinux && $this->os->getNumericVersion() <= 9 && !$this->isNodejsModuleEnabled()) {
            throw new Exception\UnsupportedOS("The default nodejs of {$this->os->getDisplayName()} doesn't provide " . self::HANDLE . ': enable a nodejs module stream that provides it (for example with dnf module enable nodejs:22)');
        }
        if ($this->os instanceof OS\Posix\RHELLike) {
            return new Packages(
                ['nodejs-libs'],
                ['nodejs-devel']
            );
        }

        throw $this->createUnknownPackagesException();
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    private function isNodejsModuleEnabled()
    {
        $fs = $this->os->getFilesystem();
        $path = '/etc/dnf/modules.d/nodejs.module';
        if (!$fs->isFile($path)) {
            return false;
        }

        return preg_match('/^state\s*=\s*enabled\s*$/m', $fs->readFile($path)) === 1;
    }
}
