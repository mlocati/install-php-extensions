<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

final class intl extends Extension implements
    Extension\BundledInPHPSource,
    Extension\RequiresSystemDependencies,
    Extension\PatchesSourceCode
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'intl';

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
            'intl',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return 'intl';
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
        return [SystemDependencies\libicu::HANDLE];
    }

    /**
     * ICU 74+ requires C++17, which is requested only since PHP 8.1.33.
     *
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PatchesSourceCode::patchSourceCode()
     * @see https://github.com/php/php-src/commit/3fdd3ed9f722d9f0aa3122b2340a611db2dfa110
     */
    public function patchSourceCode(Extension\BuildContext $context)
    {
        $sourceDirectory = $context->getSourceDirectory();
        $fs = $this->os->getFilesystem();
        if ($this->os instanceof OS\Alpine && $this->os->getNumericVersion() >= 322 && $this->php->getMajorMinorVersion() === 801 && version_compare($this->php->getBaseVersion(), '8.1.33', '<')) {
            $fs->writeFile("{$sourceDirectory}/config.m4", str_replace('PHP_CXX_COMPILE_STDCXX(11, mandatory, PHP_INTL_STDCXX)', 'PHP_CXX_COMPILE_STDCXX(17, mandatory, PHP_INTL_STDCXX)', $fs->readFile("{$sourceDirectory}/config.m4")));
        }
    }
}
