<?php

namespace IPE\Extensions;

use IPE\Exception;
use IPE\Extension;
use IPE\Filesystem\Command;
use IPE\Option;
use IPE\SystemDependencies;

/**
 * The extension links ion-c statically: we provide a fixed version of it, otherwise configure would clone its latest commit.
 */
final class ion extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\PatchesSourceCode,
    Extension\ProvidesConfigureArguments,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'ion';

    /**
     * @public
     * @var string
     */
    const IONC_VERSION = '1.1.6';

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
        return ['8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['ion'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'ion';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Tools and Utilities';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        return '@alpha';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\cmake::HANDLE, SystemDependencies\git::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * The archives of ion-c don't contain its submodules, which its CMakeLists.txt requires: we need to clone its repository.
     *
     * @see \IPE\Extension\PatchesSourceCode::patchSourceCode()
     */
    public function patchSourceCode(Extension\BuildContext $context)
    {
        $fs = $this->os->getFilesystem();
        if ($fs->isFile("{$context->getSourceDirectory()}/ion-c/CMakeLists.txt")) {
            return;
        }
        list($rc) = $fs->run(
            Command::create('git', ['clone', '-q', '-c', 'advice.detachedHead=false', '--depth', '1', '--recurse-submodules', '--branch', 'v' . self::IONC_VERSION, 'https://github.com/amzn/ion-c.git', 'ion-c'])
                ->withWorkingDirectory($context->getSourceDirectory())
                ->withOutput(Command::OUTPUT_SHOW)
        );
        if ($rc !== 0) {
            throw new Exception\IO("Failed to clone ion-c: git failed with exit code {$rc}");
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        return ['--with-ion=shared,bundled'];
    }
}
