<?php

namespace IPE\Extensions;

use IPE\Extension;

final class tokenizer extends Extension implements
    Extension\BundledInPHPSource,
    Extension\PatchesSourceCode
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'tokenizer';

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
            'tokenizer',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return 'tokenizer';
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
     * @see \IPE\Extension\PatchesSourceCode::patchSourceCode()
     */
    public function patchSourceCode(Extension\BuildContext $context)
    {
        // Its rules refer to the Zend directory of the PHP sources, which is missing in phpize builds (tokenizer_data.c is already generated)
        $makefileFrag = $context->getSourceDirectory() . '/Makefile.frag';
        $fs = $this->os->getFilesystem();
        if ($fs->isFile($makefileFrag)) {
            $fs->deleteFile($makefileFrag);
        }
    }
}
