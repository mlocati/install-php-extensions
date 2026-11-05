<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;
use IPE\OS;
use IPE\PHPInfo;

abstract class FakeDependentExtension extends Extension implements
    Extension\BundledInPHPSource,
    Extension\DependsOnExtensions
{
    /**
     * @var list<non-empty-string>
     */
    private $required;

    /**
     * @var list<non-empty-string>
     */
    private $optional;

    /**
     * @param list<non-empty-string> $required
     * @param list<non-empty-string> $optional
     */
    public function __construct(PHPInfo $php, OS $os, array $required = [], array $optional = [])
    {
        parent::__construct($php, $os);
        $this->required = $required;
        $this->optional = $optional;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return [static::getHandle()];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\BundledInPHPSource::getPHPSourceExtDirectoryName()
     */
    public static function getPHPSourceExtDirectoryName()
    {
        return 'mcrypt';
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
     * @see \IPE\Extension\DependsOnExtensions::getRequiredExtensions()
     */
    public function getRequiredExtensions($version)
    {
        return $this->required;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DependsOnExtensions::getOptionalExtensions()
     */
    public function getOptionalExtensions($version)
    {
        return $this->optional;
    }
}
