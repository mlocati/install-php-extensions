<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Filesystem\Command;
use IPE\Option;
use IPE\SystemDependencies;

final class tensor extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\PatchesSourceCode,
    Extension\ProvidesCompilerFlags,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'tensor';

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
        return ['7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['tensor'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'tensor';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Math';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        $phpVersion = $this->php->getMajorMinorVersion();
        if ($phpVersion <= 703) {
            return '2.2.3';
        }

        return $phpVersion <= 704 ? '3.0.5' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\liblapack::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesCompilerFlags::getCompilerFlags()
     */
    public function getCompilerFlags()
    {
        return '-Wno-incompatible-pointer-types';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PatchesSourceCode::patchSourceCode()
     */
    public function patchSourceCode($sourceDirectory)
    {
        // config.m4 needs the unversioned libraries, but some systems only provide the versioned ones
        $script = 'for f in /usr/lib/liblapacke.so.3 /usr/lib/*/libblas.so.3; do if [ -f "$f" ] && [ ! -e "${f%.3}" ]; then ln -s "$f" "${f%.3}"; fi; done';
        $this->os->getFilesystem()->run(Command::raw($script));
    }
}
