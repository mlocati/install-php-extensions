<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\gd;
use IPE\Filesystem;
use IPE\Option\CustomOption;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class GdTest extends TestCase
{
    /**
     * @param int $majorMinorVersion
     *
     * @return \IPE\PHPInfo&\Mockery\MockInterface
     */
    private static function createPHP($majorMinorVersion)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($majorMinorVersion);

        return $php;
    }

    /**
     * @return list<array{int, list<string>, list<string>}>
     */
    public static function provideVersions()
    {
        $common = ['lib-freetype2', 'lib-jpeg', 'lib-png', 'lib-xpm'];
        $old = ['--with-gd', '--with-jpeg-dir', '--with-png-dir', '--with-zlib-dir', '--with-xpm-dir', '--with-freetype-dir'];
        $new = ['--enable-gd', '--with-webp', '--with-jpeg', '--with-xpm', '--with-freetype'];

        return [
            [505, array_merge($old, ['--enable-gd-native-ttf', '--with-vpx-dir']), array_merge($common, ['lib-vpx'])],
            [506, array_merge($old, ['--enable-gd-native-ttf', '--with-vpx-dir']), array_merge($common, ['lib-vpx'])],
            [700, array_merge($old, ['--enable-gd-native-ttf', '--with-webp-dir']), array_merge($common, ['lib-webp'])],
            [701, array_merge($old, ['--enable-gd-native-ttf', '--with-webp-dir']), array_merge($common, ['lib-webp'])],
            [702, array_merge($old, ['--with-webp-dir']), array_merge($common, ['lib-webp'])],
            [703, array_merge($old, ['--with-webp-dir']), array_merge($common, ['lib-webp'])],
            [704, $new, array_merge($common, ['lib-webp'])],
            [800, $new, array_merge($common, ['lib-webp'])],
            [801, array_merge($new, ['--with-avif']), array_merge($common, ['lib-webp', 'lib-avif'])],
            [806, array_merge($new, ['--with-avif']), array_merge($common, ['lib-webp', 'lib-avif'])],
        ];
    }

    /**
     * @dataProvider provideVersions
     *
     * @param int $phpVersion
     * @param list<string> $expectedArguments
     * @param list<string> $expectedDependencies
     *
     * @return void
     */
    public function testVersions($phpVersion, array $expectedArguments, array $expectedDependencies)
    {
        $php = self::createPHP($phpVersion);
        $os = M::mock(OS::class);
        $gd = new gd($php, $os);
        self::assertSame($expectedArguments, $gd->getConfigureArguments(self::createBuildContext()));
        self::assertSame($expectedDependencies, $gd->getRequiredSystemDependencies('', new Values()));
    }

    /**
     * @return list<array{int, bool}>
     */
    public static function provideAvailability()
    {
        return [
            [OS\Debian::VERSION_BOOKWORM, true],
            [OS\Debian::VERSION_BULLSEYE, false],
        ];
    }

    /**
     * @dataProvider provideAvailability
     *
     * @param int $debianVersion
     * @param bool $available
     *
     * @return void
     */
    public function testWithoutAvif($debianVersion, $available)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->andReturn('');
        $fs->shouldReceive('isFile')->andReturn(false);
        $php = self::createPHP(802);
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getNumericVersion')->andReturn($debianVersion);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $options = new Values([], [gd::OPTION_WITHOUT_AVIF => true]);
        $gd = new gd($php, $os);
        self::assertSame($available, in_array('--with-avif', $gd->getConfigureArguments(self::createBuildContext('', $options)), true));
        self::assertSame($available, in_array('lib-avif', $gd->getRequiredSystemDependencies('', $options), true));
    }

    /**
     * @return void
     */
    public function testOptions()
    {
        $options = gd::getOptions();
        self::assertCount(1, $options);
        self::assertInstanceOf(CustomOption::class, $options[0]);
        self::assertSame(gd::OPTION_WITHOUT_AVIF, $options[0]->getName());
    }
}
