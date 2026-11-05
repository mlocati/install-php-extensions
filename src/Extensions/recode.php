<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\OS;
use IPE\SystemDependencies;

final class recode extends Extension implements
    Extension\BundledInPHPSource,
    Extension\DeclaresConflicts,
    Extension\DeclaresUnsupportedEnvironments,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'recode';

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
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresUnsupportedEnvironments::getUnsupportedEnvironments()
     */
    public static function getUnsupportedEnvironments()
    {
        return [
            'alpine3.11..3.15' => 'the recode 3.7 library provided by this system does not work',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return [
            'recode',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresConflicts::getConflictingExtensions()
     */
    public function getConflictingExtensions($version)
    {
        if ($this->os instanceof OS\Alpine) {
            // On Alpine the second one to be loaded fails.
            return [imap::HANDLE];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return 'recode';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getBundledPHPVersions()
     */
    public static function getBundledPHPVersions()
    {
        return '<7.4';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\librecode::HANDLE];
    }
}
