<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\Service\Regex;
use IPE\SystemDependencies;

final class rrd extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\PatchesSourceCode,
    Extension\ProvidesConfigureEnvironment,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'rrd';

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
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['rrd'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'rrd';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Processing';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        return $this->php->getMajorMinorVersion() <= 506 ? '1.1.3' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\librrd::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureEnvironment::getConfigureEnvironment()
     */
    public function getConfigureEnvironment(Extension\BuildContext $context)
    {
        return ['CFLAGS' => '-Wno-incompatible-pointer-types'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PatchesSourceCode::patchSourceCode()
     */
    public function patchSourceCode(Extension\BuildContext $context)
    {
        // Old versions call rrdc_disconnect() without including its header
        $fs = $this->os->getFilesystem();
        $file = "{$context->getSourceDirectory()}/rrd.c";
        $code = $fs->readFile($file);
        if (strpos($code, 'rrd_client.h') === false) {
            $fs->writeFile($file, Regex::replace('/^#include\s*<rrd\.h>/m', "$0\n#include <rrd_client.h>", $code, 1));
        }
    }
}
