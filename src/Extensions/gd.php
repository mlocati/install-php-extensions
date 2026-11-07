<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\SystemDependencies;

final class gd extends Extension implements
    Extension\BundledInPHPSource,
    Extension\DeclaresOptions,
    Extension\RequiresSystemDependencies,
    Extension\ProvidesConfigureArguments
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'gd';

    /**
     * @public
     * @var string
     */
    const OPTION_WITHOUT_AVIF = 'without-avif';

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
            'gd',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return 'gd';
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
     * @see \IPE\Extension\DeclaresOptions::getOptions()
     */
    public static function getOptions()
    {
        return [
            new Option\CustomOption(self::OPTION_WITHOUT_AVIF, 'Skip the AVIF support if it would require compiling libaom, libyuv and libavif', false),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        $result = [
            SystemDependencies\libfreetype2::HANDLE,
            SystemDependencies\libjpeg::HANDLE,
            SystemDependencies\libpng::HANDLE,
            SystemDependencies\libxpm::HANDLE,
        ];
        if ($this->php->getMajorMinorVersion() <= 506) {
            $result[] = SystemDependencies\libvpx::HANDLE;
        }
        if ($this->php->getMajorMinorVersion() >= 700) {
            $result[] = SystemDependencies\libwebp::HANDLE;
        }
        if ($this->isAvifEnabled($options)) {
            $result[] = SystemDependencies\libavif::HANDLE;
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
        $phpVersion = $this->php->getMajorMinorVersion();
        if ($phpVersion <= 506) {
            return ['--with-gd', '--with-jpeg-dir', '--with-png-dir', '--with-zlib-dir', '--with-xpm-dir', '--with-freetype-dir', '--enable-gd-native-ttf', '--with-vpx-dir'];
        }
        if ($phpVersion <= 701) {
            return ['--with-gd', '--with-jpeg-dir', '--with-png-dir', '--with-zlib-dir', '--with-xpm-dir', '--with-freetype-dir', '--enable-gd-native-ttf', '--with-webp-dir'];
        }
        if ($phpVersion <= 703) {
            return ['--with-gd', '--with-jpeg-dir', '--with-png-dir', '--with-zlib-dir', '--with-xpm-dir', '--with-freetype-dir', '--with-webp-dir'];
        }
        $result = ['--enable-gd', '--with-webp', '--with-jpeg', '--with-xpm', '--with-freetype'];
        if ($this->isAvifEnabled($context->getOptionValues())) {
            $result[] = '--with-avif';
        }

        return $result;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    private function isAvifEnabled(Option\Values $options)
    {
        if ($this->php->getMajorMinorVersion() < 801) {
            return false;
        }

        return !$options->hasCustomValue(self::OPTION_WITHOUT_AVIF) || SystemDependencies\libavif::isAvailableWithoutCompiling($this->php, $this->os);
    }
}
