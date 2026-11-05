<?php

namespace IPE\Test\Installer;

use IPE\Extension\Manifest;
use IPE\Extension\Resolved;
use IPE\Extension\Skipped;
use IPE\Installer\InstalledDependencies;
use IPE\Installer\Report;
use IPE\Installer\Report\CompiledLibrary;
use IPE\Installer\Report\InstalledExtension;
use IPE\Option\Values;
use IPE\Service\JSON;
use IPE\Test\Support\TestCase;

final class ReportTest extends TestCase
{
    /**
     * @return mixed
     */
    private static function toJsonArray(Report $report)
    {
        return JSON::decode(JSON::encode($report), true);
    }

    /**
     * @return void
     */
    public function testEmptyReport()
    {
        self::assertSame(
            [
                'extensions' => [],
                'skippedExtensions' => [],
                'compiledLibraries' => [],
                'systemPackages' => ['persistent' => [], 'volatile' => [], 'previouslyInstalled' => []],
            ],
            self::toJsonArray(new Report())
        );
    }

    /**
     * @return void
     */
    public function testJsonSerialization()
    {
        $resolved = (new Resolved(null, '/src', '1.2.3', Resolved::ORIGIN_PECL, new Manifest(Manifest::TYPE_PECL, 'foo', 'foo'), new Values([], ['opt' => 'value'])))->withRequirers(false, ['bar']);
        $installed = (new InstalledExtension($resolved, InstalledExtension::METHOD_COMPILED, ['/ext/foo.so'], false, ['/ini/20-foo.ini' => true], ['foo'], ['--enable-foo'], 1.23456))
            ->withIniFiles(['/ini/20-foo.ini' => false])
            ->withEnableScript('/usr/bin/ipe-enable-foo')
        ;
        $report = new Report(
            new InstalledDependencies(['libfoo'], ['libfoo-dev'], ['gcc']),
            [new CompiledLibrary('lib-foo', '4.5', 'https://example.com/foo.tar.gz')],
            [$installed],
            [new Skipped('baz', Skipped::REASON_ALREADY_INSTALLED, 'The extension baz is already installed - skipping')]
        );
        self::assertSame(
            [
                'extensions' => [
                    [
                        'name' => 'foo',
                        'version' => '1.2.3',
                        'origin' => Resolved::ORIGIN_PECL,
                        'asked' => false,
                        'requiredBy' => ['bar'],
                        'method' => InstalledExtension::METHOD_COMPILED,
                        'options' => ['opt' => 'value'],
                        'configureArguments' => ['--enable-foo'],
                        'moduleFiles' => ['/ext/foo.so'],
                        'zendExtension' => false,
                        'iniFiles' => [['path' => '/ini/20-foo.ini', 'active' => false]],
                        'loadedModules' => ['foo'],
                        'enableScript' => '/usr/bin/ipe-enable-foo',
                        'duration' => 1.235,
                    ],
                ],
                'skippedExtensions' => [
                    ['name' => 'baz', 'reason' => Skipped::REASON_ALREADY_INSTALLED, 'message' => 'The extension baz is already installed - skipping'],
                ],
                'compiledLibraries' => [
                    ['handle' => 'lib-foo', 'version' => '4.5', 'sourceURL' => 'https://example.com/foo.tar.gz'],
                ],
                'systemPackages' => ['persistent' => ['libfoo'], 'volatile' => ['libfoo-dev'], 'previouslyInstalled' => ['gcc']],
            ],
            self::toJsonArray($report)
        );
    }

    /**
     * @return void
     */
    public function testEmptyOptionsAreSerializedAsObject()
    {
        $resolved = new Resolved(null, '/src', '1.2.3', Resolved::ORIGIN_PECL, new Manifest(Manifest::TYPE_PECL, 'foo', 'foo'), new Values([], []));
        $installed = new InstalledExtension($resolved, InstalledExtension::METHOD_COMPILED, [], null, [], [], [], 0.0);
        self::assertStringContainsString('"options":{}', JSON::encode($installed));
    }
}
