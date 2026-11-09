<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\dom;
use IPE\Filesystem;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class DomTest extends TestCase
{
    /**
     * @return array<array{int, non-empty-string, string}> the PHP version, the source directory of dom, and the expected preprocessor flags
     */
    public static function providePreprocessorFlags()
    {
        return [
            [505, '/tmp/php-5.5.38/ext/dom', ''],
            [804, '/tmp/php-8.4.1/ext/dom', ''],
            [805, '/tmp/php-8.5.4/ext/dom', '-I</tmp/php-8.5.4/ext/lexbor> -I</tmp/php-8.5.4>'],
            [806, '/tmp/my dir/php-8.6.0/ext/dom', '-I</tmp/my dir/php-8.6.0/ext/lexbor> -I</tmp/my dir/php-8.6.0>'],
        ];
    }

    /**
     * @dataProvider providePreprocessorFlags
     *
     * @param int $phpVersion
     * @param non-empty-string $sourceDirectory
     * @param string $expected
     *
     * @return void
     */
    public function testPreprocessorFlags($phpVersion, $sourceDirectory, $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('escapeShellArg')->andReturnUsing([self::class, 'fakeEscapeShellArg']);
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        self::assertSame($expected, (new dom($php, $os))->getPreprocessorFlags(self::createBuildContext('', null, $sourceDirectory)));
    }

    /**
     * @return void
     */
    public function testCompilerFlags()
    {
        foreach ([702 => '-Wno-incompatible-pointer-types', 703 => ''] as $phpVersion => $expected) {
            $php = M::mock(PHPInfo::class);
            $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
            self::assertSame($expected, (new dom($php, M::mock(OS::class)))->getCompilerFlags(self::createBuildContext()));
        }
    }

    /**
     * Shows which strings have been escaped.
     *
     * @param string $argument
     *
     * @return string
     */
    public static function fakeEscapeShellArg($argument)
    {
        return "<{$argument}>";
    }
}
