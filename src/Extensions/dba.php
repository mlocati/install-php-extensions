<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

final class dba extends Extension implements
    Extension\BundledInPHPSource,
    Extension\RequiresSystemDependencies,
    Extension\ProvidesConfigureArguments,
    Extension\PatchesSourceCode
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'dba';

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return self::HANDLE;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getSupportedPHPVersions()
     */
    public static function getSupportedPHPVersions()
    {
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5', '8.6'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return [
            'dba',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return 'dba';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getBundledPHPVersions()
     */
    public static function getBundledPHPVersions()
    {
        return '*';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        // Besides being useful, these handlers are required when the php_config.h of the PHP packages enables them (like on Alpine and RHEL-like systems)
        $result = [];
        if ($this->usesBerkeleyDB()) {
            $result[] = SystemDependencies\libdb::HANDLE;
        }
        if ($this->php->getMajorMinorVersion() >= 702) {
            $result[] = SystemDependencies\liblmdb::HANDLE;
        }
        // Alpine doesn't provide Tokyo Cabinet
        if ($this->os instanceof OS\Posix\DebianLike || $this->os instanceof OS\Posix\RHELLike || $this->os instanceof OS\MacOS) {
            $result[] = SystemDependencies\libtokyocabinet::HANDLE;
        }
        // QDBM and GDBM can't be used together
        if ($this->usesQDBM()) {
            $result[] = SystemDependencies\libqdbm::HANDLE;
        } else {
            $result[] = SystemDependencies\libgdbm::HANDLE;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        $result = [];
        if ($this->os instanceof OS\MacOS) {
            // configure looks for the headers only in /usr/local and /usr
            $homebrew = $this->os->getSystemPackager();
            $result[] = '--with-db4=' . $homebrew->getFormulaPrefix('berkeley-db@5');
            if ($this->php->getMajorMinorVersion() >= 702) {
                $result[] = '--with-lmdb=' . $homebrew->getFormulaPrefix('lmdb');
            }
            $result[] = '--with-tcadb=' . $homebrew->getFormulaPrefix('tokyo-cabinet');
            $result[] = '--with-gdbm=' . $homebrew->getFormulaPrefix('gdbm');
        } else {
            if ($this->usesBerkeleyDB()) {
                $result[] = '--with-db4';
            }
            // Not every system provides every library (see getRequiredSystemDependencies())
            $fs = $this->os->getFilesystem();
            if ($this->php->getMajorMinorVersion() >= 702 && $fs->isFile('/usr/include/lmdb.h')) {
                $result[] = '--with-lmdb';
            }
            if ($fs->isFile('/usr/include/tcadb.h')) {
                $result[] = '--with-tcadb';
            }
            if ($this->php->getMajorMinorVersion() >= 704 && $fs->isFile('/usr/include/qdbm/depot.h')) {
                $result[] = '--with-qdbm';
            } elseif ($fs->isFile('/usr/include/gdbm.h')) {
                $result[] = '--with-gdbm';
            }
        }

        return $result;
    }

    /**
     * @return bool
     */
    private function usesBerkeleyDB()
    {
        // The Berkeley DB of Wolfi has a major version (2026) that configure rejects
        return !$this->os instanceof OS\Wolfi;
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\UnsupportedOS
     *
     * @return bool
     */
    private function usesQDBM()
    {
        // Before PHP 7.4, configure doesn't look for the headers in /usr/include/qdbm, where the operating systems put them
        if ($this->php->getMajorMinorVersion() < 704) {
            return false;
        }
        if ($this->os instanceof OS\Posix\AlpineLike) {
            return false;
        }
        if ($this->os instanceof OS\Posix\DebianLike) {
            return true;
        }
        // Among the RHEL-like systems, QDBM is provided by Fedora and by Remi (qdbm-devel only by its remi repository)
        if ($this->os instanceof OS\Posix\RHELLike) {
            return $this->os->getSystemPackager()->findAvailablePackage('qdbm') !== null;
        }
        // Homebrew deprecated QDBM
        if ($this->os instanceof OS\MacOS) {
            return false;
        }

        throw $this->createUnknownOSException();
    }

    /**
     * The configure script of PHP 5.5 doesn't look for Berkeley DB 5.3, which is the only version provided by the operating systems.
     *
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PatchesSourceCode::patchSourceCode()
     */
    public function patchSourceCode(Extension\BuildContext $context)
    {
        $sourceDirectory = $context->getSourceDirectory();
        $fs = $this->os->getFilesystem();
        if ($this->php->getMajorMinorVersion() <= 505) {
            $fs->writeFile("{$sourceDirectory}/config.m4", str_replace('PHP_DBA_DB_CHECK(4, db-5.1 ', 'PHP_DBA_DB_CHECK(4, db-5.3 db-5.1 ', $fs->readFile("{$sourceDirectory}/config.m4")));
        }
    }
}
