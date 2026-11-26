<?php

namespace IPE\Test\SystemPackager;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\SystemPackager\Homebrew;
use IPE\SystemPackager\Package;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class HomebrewTest extends TestCase
{
    /**
     * @var non-empty-string
     */
    const BREW = '/opt/homebrew/bin/brew';

    /**
     * @var list<string>
     */
    private static $tapInfoArguments = ['tap-info', '--json', 'shivammathur/extensions', 'shivammathur/php'];

    /**
     * @var array<string, mixed>
     */
    private static $extensionsTapInfo = [
        'name' => 'shivammathur/extensions',
        'installed' => true,
        'trusted' => true,
        'formula_names' => [
            'shivammathur/extensions/imap-uw',
            'shivammathur/extensions/phpredis@8.4',
            'shivammathur/extensions/xdebug@8.4',
            'shivammathur/extensions/xdebug@8.5',
        ],
    ];

    /**
     * @var array<string, mixed>
     */
    private static $phpTapInfo = [
        'name' => 'shivammathur/php',
        'installed' => true,
        'trusted' => true,
        'formula_names' => ['shivammathur/php/php@8.4'],
    ];

    /**
     * @param string $brew the path of brew (empty string if it's not installed)
     *
     * @return \IPE\Filesystem&\Mockery\MockInterface
     */
    private static function createFilesystem($brew = self::BREW)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn($brew);
        $fs->shouldReceive('isFile')->with('/opt/homebrew/bin/brew')->andReturn(false);
        $fs->shouldReceive('isFile')->with('/usr/local/bin/brew')->andReturn(false);

        return $fs;
    }

    /**
     * @param \IPE\Filesystem&\Mockery\MockInterface $fs
     * @param list<string> $arguments
     * @param int $rc
     * @param string $output
     * @param non-empty-string $outputMode one of the Command::OUTPUT_ constants
     *
     * @return void
     */
    private static function expectBrew($fs, array $arguments, $rc, $output, $outputMode = Command::OUTPUT_CAPTURE_STDOUT)
    {
        $command = Command::create(self::BREW, $arguments)
            ->withOutput($outputMode)
            ->withEnvironment([
                'HOMEBREW_NO_AUTO_UPDATE' => '1',
                'HOMEBREW_NO_INSTALL_UPGRADE' => '1',
                'HOMEBREW_NO_INSTALLED_DEPENDENTS_CHECK' => '1',
                'HOMEBREW_NO_INSTALL_CLEANUP' => '1',
                'HOMEBREW_NO_ENV_HINTS' => '1',
            ])
        ;
        $fs->shouldReceive('run')->once()->with(self::isCommand($command))->andReturn([$rc, $output]);
    }

    /**
     * @param \IPE\Filesystem&\Mockery\MockInterface $fs
     *
     * @return \IPE\SystemPackager\Homebrew
     */
    private static function createHomebrew($fs)
    {
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);

        return new Homebrew($os);
    }

    /**
     * @return void
     */
    public function testExpandPackageList()
    {
        $fs = self::createFilesystem();
        self::expectBrew($fs, ['deps', '--union', '--formula', 'libzip', 'autoconf'], 0, "lz4\nm4\nxz\nzstd\n");
        self::expectBrew($fs, ['list', '--formula', '-1'], 0, "lz4\nphp\nxz\n");
        self::expectBrew($fs, ['info', '--json=v2', '--formula', 'libzip', 'autoconf', 'm4', 'zstd'], 0, (string) json_encode([
            'formulae' => [
                ['name' => 'libzip', 'versions' => ['stable' => '1.12.1'], 'revision' => 0],
                ['name' => 'autoconf', 'versions' => ['stable' => '2.73'], 'revision' => 0],
                ['name' => 'm4', 'versions' => ['stable' => '1.4.21'], 'revision' => 0],
                ['name' => 'zstd', 'versions' => ['stable' => '1.5.7'], 'revision' => 1],
            ],
            'casks' => [],
        ]));
        self::assertSame(
            ['libzip' => '1.12.1', 'autoconf' => '2.73', 'm4' => '1.4.21', 'zstd' => '1.5.7_1'],
            self::createHomebrew($fs)->expandPackageList(['libzip', 'autoconf'])
        );
    }

    /**
     * @return void
     */
    public function testExpandPackageListOfInstalledFormulae()
    {
        $fs = self::createFilesystem();
        self::expectBrew($fs, ['deps', '--union', '--formula', 'pkgconf'], 0, '');
        self::expectBrew($fs, ['list', '--formula', '-1'], 0, "php\npkgconf\n");
        self::assertSame([], self::createHomebrew($fs)->expandPackageList(['pkgconf']));
    }

    /**
     * @return void
     */
    public function testExpandPackageListWithAliases()
    {
        $fs = self::createFilesystem();
        self::expectBrew($fs, ['deps', '--union', '--formula', 'icu4c', 'openssl'], 0, "ca-certificates\n");
        self::expectBrew($fs, ['list', '--formula', '-1'], 0, "ca-certificates\nicu4c@79\n");
        self::expectBrew($fs, ['info', '--json=v2', '--formula', 'icu4c', 'openssl'], 0, (string) json_encode([
            'formulae' => [
                ['name' => 'icu4c@79', 'full_name' => 'icu4c@79', 'versions' => ['stable' => '79.1'], 'revision' => 0],
                ['name' => 'openssl@4', 'full_name' => 'openssl@4', 'versions' => ['stable' => '4.1.0'], 'revision' => 0],
            ],
        ]));
        self::assertSame(['openssl@4' => '4.1.0'], self::createHomebrew($fs)->expandPackageList(['icu4c', 'openssl']));
    }

    /**
     * @return void
     */
    public function testFindAvailablePackage()
    {
        $fs = self::createFilesystem();
        self::expectBrew($fs, ['info', '--json=v2', '--formula', 'liblzf'], 0, (string) json_encode(['formulae' => [['name' => 'liblzf', 'versions' => ['stable' => '3.6'], 'revision' => 0]]]));
        self::expectBrew($fs, ['info', '--json=v2', '--formula', 'not-a-formula'], 1, '');
        $homebrew = self::createHomebrew($fs);
        $package = $homebrew->findAvailablePackage('liblzf');
        self::assertNotNull($package);
        self::assertSame('liblzf', $package->getName());
        self::assertSame('3.6', $package->getVersion());
        self::assertNull($homebrew->findAvailablePackage('not-a-formula'));
    }

    /**
     * @return void
     */
    public function testInstalledPackageOwningFile()
    {
        $fs = self::createFilesystem();
        self::expectBrew($fs, ['--cellar'], 0, "/opt/homebrew/Cellar\n");
        self::expectBrew($fs, self::$tapInfoArguments, 0, (string) json_encode([['name' => 'shivammathur/extensions', 'installed' => false, 'formula_names' => []], self::$phpTapInfo]));
        $fs->shouldReceive('getRealPath')->with('/opt/homebrew/bin/php')->andReturn('/opt/homebrew/Cellar/php@8.4/8.4.14_1/bin/php');
        $fs->shouldReceive('getRealPath')->with('/usr/local/bin/php')->andReturn('/usr/local/bin/php');
        $homebrew = self::createHomebrew($fs);
        $package = $homebrew->getInstalledPackageOwningFile('/opt/homebrew/bin/php');
        self::assertNotNull($package);
        self::assertSame('php@8.4', $package->getName());
        self::assertSame('8.4.14_1', $package->getVersion());
        self::assertSame('php@8.4', $homebrew->getPHPDevelopmentPackageName($package));
        self::assertSame([], $homebrew->getPHPExtensionPackageCandidates($package, 'xdebug'));
        self::assertNull($homebrew->getInstalledPackageOwningFile('/usr/local/bin/php'));
    }

    /**
     * @return void
     */
    public function testExtensionsTapCandidates()
    {
        $fs = self::createFilesystem();
        self::expectBrew($fs, self::$tapInfoArguments, 0, (string) json_encode([self::$extensionsTapInfo, self::$phpTapInfo]));
        $homebrew = self::createHomebrew($fs);
        $php84 = new Package('php@8.4', '8.4.14_1', 'php@8.4');
        self::assertSame(['shivammathur/extensions/xdebug@8.4'], $homebrew->getPHPExtensionPackageCandidates($php84, 'Xdebug'));
        self::assertSame([], $homebrew->getPHPExtensionPackageCandidates($php84, 'redis'));
        self::assertSame(['shivammathur/extensions/phpredis@8.4'], $homebrew->getExtensionFormulae($php84, ['phpredis', 'redis']));
        self::assertSame([], $homebrew->getPHPExtensionPackageCandidates($php84, 'gd'));
        self::assertSame(['shivammathur/extensions/xdebug@8.5'], $homebrew->getPHPExtensionPackageCandidates(new Package('php', '8.5.0_1', 'php'), 'xdebug'));
        self::assertSame([], $homebrew->getPHPExtensionPackageCandidates(new Package('php@8.4-zts', '8.4.14', 'php@8.4-zts'), 'xdebug'));
        self::assertSame('shivammathur/extensions/imap-uw', $homebrew->getExtensionsTapFormula('imap-uw'));
        self::assertSame('', $homebrew->getExtensionsTapFormula('xdebug'));
    }

    /**
     * @return array<array{list<array<string, mixed>>}>
     */
    public static function provideUnusableExtensionsTaps()
    {
        return [
            [[['name' => 'shivammathur/extensions', 'installed' => false, 'formula_names' => []], self::$phpTapInfo]],
            [[['trusted' => false] + self::$extensionsTapInfo, self::$phpTapInfo]],
            [[self::$extensionsTapInfo, ['name' => 'shivammathur/php', 'installed' => false, 'formula_names' => []]]],
            [[self::$extensionsTapInfo, ['trusted' => false] + self::$phpTapInfo]],
        ];
    }

    /**
     * @dataProvider provideUnusableExtensionsTaps
     *
     * @param list<array<string, mixed>> $tapInfo
     *
     * @return void
     */
    public function testUnusableExtensionsTap(array $tapInfo)
    {
        $fs = self::createFilesystem();
        self::expectBrew($fs, self::$tapInfoArguments, 0, (string) json_encode($tapInfo));
        $homebrew = self::createHomebrew($fs);
        self::assertSame([], $homebrew->getPHPExtensionPackageCandidates(new Package('php@8.4', '8.4.14_1', 'php@8.4'), 'xdebug'));
        self::assertSame('', $homebrew->getExtensionsTapFormula('imap-uw'));
    }

    /**
     * @return void
     */
    public function testRemoveOnlyInstalledFormulae()
    {
        $fs = self::createFilesystem();
        self::expectBrew($fs, ['list', '--formula', '-1'], 0, "liblzf\nphp@8.4\n");
        self::expectBrew($fs, ['uninstall', '--formula', 'liblzf'], 0, '', Command::OUTPUT_SHOW);
        self::createHomebrew($fs)->removePackages(['shivammathur/extensions/phpredis@8.4', 'liblzf']);
    }

    /**
     * @return void
     */
    public function testFindAvailableTapFormula()
    {
        $fs = self::createFilesystem();
        self::expectBrew($fs, ['info', '--json=v2', '--formula', 'shivammathur/extensions/xdebug@8.4'], 0, (string) json_encode(['formulae' => [['name' => 'xdebug@8.4', 'full_name' => 'shivammathur/extensions/xdebug@8.4', 'versions' => ['stable' => '3.5.3'], 'revision' => 0]]]));
        $package = self::createHomebrew($fs)->findAvailablePackage('shivammathur/extensions/xdebug@8.4');
        self::assertNotNull($package);
        self::assertSame('shivammathur/extensions/xdebug@8.4', $package->getName());
        self::assertSame('3.5.3', $package->getVersion());
    }

    /**
     * @return void
     */
    public function testBuildEnvironment()
    {
        $fs = self::createFilesystem();
        self::expectBrew($fs, ['--prefix'], 0, "/opt/homebrew\n");
        self::expectBrew($fs, ['info', '--json=v2', '--formula', 'liblzf', 'zlib'], 0, (string) json_encode([
            'formulae' => [
                ['name' => 'liblzf', 'keg_only' => false],
                ['name' => 'zlib', 'keg_only' => true],
            ],
        ]));
        self::expectBrew($fs, ['deps', '--formula', '--union', '--full-name', 'liblzf', 'zlib'], 0, "libarchive\nlz4\nzlib\n");
        self::expectBrew($fs, ['info', '--json=v2', '--formula', 'libarchive', 'lz4'], 0, (string) json_encode([
            'formulae' => [
                ['name' => 'libarchive', 'keg_only' => true],
                ['name' => 'lz4', 'keg_only' => false],
            ],
        ]));
        $homebrew = self::createHomebrew($fs);
        self::assertSame(
            [
                'PKG_CONFIG_PATH' => '/opt/homebrew/opt/zlib/lib/pkgconfig:/opt/homebrew/opt/libarchive/lib/pkgconfig',
                'CPPFLAGS' => '-I/opt/homebrew/opt/zlib/include -I/opt/homebrew/include',
                'LDFLAGS' => '-L/opt/homebrew/opt/zlib/lib -L/opt/homebrew/lib',
            ],
            $homebrew->getBuildEnvironment(['liblzf', 'zlib'])
        );
        self::assertSame(['CPPFLAGS' => '-I/opt/homebrew/include', 'LDFLAGS' => '-L/opt/homebrew/lib'], $homebrew->getBuildEnvironment([]));
        self::assertSame('/opt/homebrew/opt/php@8.4', $homebrew->getFormulaPrefix('shivammathur/php/php@8.4'));
    }

    /**
     * @return void
     */
    public function testExposeExtensionHeaders()
    {
        $fs = self::createFilesystem();
        self::expectBrew($fs, ['--prefix'], 0, "/opt/homebrew\n");
        $headersDir = '/opt/homebrew/opt/phpredis@8.4/include/php/ext';
        $fs->shouldReceive('isDirectory')->once()->with($headersDir)->andReturn(true);
        $fs->shouldReceive('listDirectoryContents')->once()->with($headersDir)->andReturn(['other', 'redis@8.4']);
        $fs->shouldReceive('exists')->once()->with('/php/include/ext/redis')->andReturn(false);
        $fs->shouldReceive('isSymlink')->once()->with('/php/include/ext/redis')->andReturn(false);
        $fs->shouldReceive('createSymlink')->once()->with("{$headersDir}/redis@8.4", '/php/include/ext/redis');
        $homebrew = self::createHomebrew($fs);
        self::assertSame(['/php/include/ext/redis'], $homebrew->exposeExtensionHeaders('shivammathur/extensions/phpredis@8.4', '/php/include'));
        // Not a formula of the extensions tap
        self::assertSame([], $homebrew->exposeExtensionHeaders('liblzf', '/php/include'));
        $fs->shouldReceive('isDirectory')->once()->with('/opt/homebrew/opt/raphf@8.4/include/php/ext')->andReturn(false);
        self::assertSame([], $homebrew->exposeExtensionHeaders('shivammathur/extensions/raphf@8.4', '/php/include'));
        $fs->shouldReceive('isDirectory')->once()->with('/opt/homebrew/opt/igbinary@8.4/include/php/ext')->andReturn(true);
        $fs->shouldReceive('listDirectoryContents')->once()->with('/opt/homebrew/opt/igbinary@8.4/include/php/ext')->andReturn(['igbinary@8.4']);
        $fs->shouldReceive('exists')->once()->with('/php/include/ext/igbinary')->andReturn(true);
        self::assertSame([], $homebrew->exposeExtensionHeaders('shivammathur/extensions/igbinary@8.4', '/php/include'));
    }

    /**
     * @return void
     */
    public function testWithoutHomebrew()
    {
        $fs = self::createFilesystem('');
        $homebrew = self::createHomebrew($fs);
        self::assertNull($homebrew->getInstalledPackageOwningFile('/usr/local/bin/php'));
        self::assertSame('', $homebrew->getFormulaPrefix('liblzf'));
        self::assertSame([], $homebrew->getBuildEnvironment(['liblzf']));
        $this->expectException(Exception\SystemPackager::class);
        $this->expectExceptionMessage('Homebrew is required');
        $homebrew->listInstalledPackages();
    }

    /**
     * @return void
     */
    public function testPhpizeRequirements()
    {
        $fs = self::createFilesystem();
        $command = Command::create('xcode-select', ['--print-path'])->withOutput(Command::OUTPUT_DISCARD);
        $fs->shouldReceive('run')->once()->with(self::isCommand($command))->andReturn([0, '']);
        self::assertSame(['autoconf', 'pkgconf'], self::createHomebrew($fs)->getPhpizeRequirements());
        $fs = self::createFilesystem();
        $fs->shouldReceive('run')->once()->with(self::isCommand($command))->andReturn([2, '']);
        $this->expectException(Exception\SystemPackager::class);
        $this->expectExceptionMessage('xcode-select --install');
        self::createHomebrew($fs)->getPhpizeRequirements();
    }

    /**
     * @return array<array{non-empty-string, string}>
     */
    public static function provideUpstreamVersions()
    {
        return [
            ['8.4.14', '8.4.14'],
            ['8.4.14_1', '8.4.14'],
            ['1.5.7_12', '1.5.7'],
            ['HEAD-1234567', ''],
        ];
    }

    /**
     * @dataProvider provideUpstreamVersions
     *
     * @param non-empty-string $version
     * @param string $expected
     *
     * @return void
     */
    public function testUpstreamVersion($version, $expected)
    {
        self::assertSame($expected, self::createHomebrew(self::createFilesystem())->getUpstreamVersion($version));
    }
}
