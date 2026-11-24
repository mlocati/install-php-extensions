<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\lz4;
use IPE\Extensions\snappy;
use IPE\Extensions\spx;
use IPE\Extensions\wikidiff2;
use IPE\Extensions\yajl_stream;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ComposerVersionsTest extends TestCase
{
    /**
     * @return array<array{class-string<\IPE\Extension&\IPE\Extension\IdentifiedByComposerPackage>, non-empty-string, string}>
     */
    public static function provideComposerVersions()
    {
        return [
            [lz4::class, '0.8.0', '0.8.0'],
            [lz4::class, '0.4.4+pie', '0.4.4'],
            [lz4::class, 'dev-master', ''],
            [snappy::class, '0.2.3', '0.2.3'],
            [spx::class, 'v0.4.22', '0.4.22'],
            [spx::class, 'v0.4.22-beta1', ''],
            [wikidiff2::class, '1.14.2', '1.14.2'],
            [yajl_stream::class, 'v0.1.0', '0.1.0'],
        ];
    }

    /**
     * @dataProvider provideComposerVersions
     *
     * @param class-string<\IPE\Extension&\IPE\Extension\IdentifiedByComposerPackage> $class
     * @param non-empty-string $composerVersion
     * @param string $expected
     *
     * @return void
     */
    public function testComposerVersion($class, $composerVersion, $expected)
    {
        $extension = new $class(M::mock(PHPInfo::class), M::mock(OS::class));
        self::assertSame($expected, $extension->getExtensionVersionFromComposerVersion($composerVersion));
    }
}
