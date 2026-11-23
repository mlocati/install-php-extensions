<?php

namespace IPE\Test\Extension;

use IPE\Extension\Manifest;
use IPE\Extension\Resolved;
use IPE\Option\ConfigureOption;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\ResolverTest\FakeExtension;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ResolvedTest extends TestCase
{
    /**
     * @return \IPE\Extension\Manifest
     */
    private static function createManifest()
    {
        $manifest = new Manifest(Manifest::TYPE_PECL, 'fakeext', 'fakeext');
        $manifest->addConfigureOption(new ConfigureOption('with-fake-dir', 'Ignored', true, '/usr'));
        $manifest->addConfigureOption(new ConfigureOption('enable-fake-lz4', 'Enable lz4', null, 'no'));

        return $manifest;
    }

    /**
     * @return array<array{non-empty-string, string|null}>
     */
    public static function provideOrigins()
    {
        return [
            [Resolved::ORIGIN_PECL, 'no'],
            // The configure scripts of the bundled extensions know their defaults better than their manifests
            [Resolved::ORIGIN_BUNDLED, null],
        ];
    }

    /**
     * @dataProvider provideOrigins
     *
     * @param non-empty-string $origin
     * @param string|null $expectedLz4Default
     *
     * @return void
     */
    public function testDeclaredOptions($origin, $expectedLz4Default)
    {
        $known = new FakeExtension(M::mock(PHPInfo::class), M::mock(OS::class));
        $options = (new Resolved($known, '/src', '1.0.0', $origin, self::createManifest()))->getDeclaredOptions();

        self::assertSame(['enable-fake-debug', 'with-fake-dir', 'variant', 'enable-fake-lz4'], array_map(static function (\IPE\Option $option) { return $option->getName(); }, $options));
        // The options declared by IPE win over the ones of the manifest
        self::assertSame('Path to the fake library', $options[1]->getDescription());
        self::assertNull($options[1]->getDefault());
        self::assertSame('Enable lz4', $options[3]->getDescription());
        self::assertSame($expectedLz4Default, $options[3]->getDefault());
    }

    /**
     * @return void
     */
    public function testNoDeclaredOptions()
    {
        self::assertSame([], (new Resolved(null, '/src', '', Resolved::ORIGIN_DIRECTORY))->getDeclaredOptions());
    }
}
