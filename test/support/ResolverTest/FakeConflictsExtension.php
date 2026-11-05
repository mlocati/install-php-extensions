<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;
use IPE\OS;
use IPE\PHPInfo;

final class FakeConflictsExtension extends Extension implements
    Extension\DeclaresConflicts
{
    /**
     * @var list<non-empty-string>
     */
    private $conflicts;

    /**
     * @param list<non-empty-string> $conflicts
     */
    public function __construct(PHPInfo $php, OS $os, array $conflicts)
    {
        parent::__construct($php, $os);
        $this->conflicts = $conflicts;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return 'conflicting';
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
        return ['conflicting'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresConflicts::getConflictingExtensions()
     */
    public function getConflictingExtensions($version)
    {
        return $this->conflicts;
    }
}
