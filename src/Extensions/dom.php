<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\SystemDependencies;

final class dom extends Extension implements
    Extension\BundledInPHPSource,
    Extension\PatchesSourceCode,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'dom';

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
            'dom',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return 'dom';
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
        return [SystemDependencies\libxml2::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PatchesSourceCode::patchSourceCode()
     */
    public function patchSourceCode(Extension\BuildContext $context)
    {
        if ($this->php->getMajorMinorVersion() < 805) {
            return;
        }
        $sourceDirectory = $context->getSourceDirectory();
        // Since PHP 8.5 the lexbor headers are in ext/lexbor, and PHP doesn't install them: dom includes them both as lexbor/... and as ext/lexbor/lexbor/...
        $fs = $this->os->getFilesystem();
        $configM4 = "{$sourceDirectory}/config.m4";
        $phpSourceDirectory = dirname(dirname($sourceDirectory));
        $fs->writeFile($configM4, rtrim($fs->readFile($configM4)) . "\nPHP_ADD_INCLUDE([{$phpSourceDirectory}/ext/lexbor])\nPHP_ADD_INCLUDE([{$phpSourceDirectory}])\n");
    }
}
