<?php

namespace IPE\Test\SystemPackager;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\SystemPackager\Homebrew;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class HomebrewTest extends TestCase
{
    /**
     * @var non-empty-string
     */
    const BREW = '/opt/homebrew/bin/brew';

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
     *
     * @return void
     */
    private static function expectBrew($fs, array $arguments, $rc, $output)
    {
        $command = Command::create(self::BREW, $arguments)
            ->withOutput(Command::OUTPUT_CAPTURE_STDOUT)
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
        $homebrew = self::createHomebrew($fs);
        self::assertSame(
            [
                'PKG_CONFIG_PATH' => '/opt/homebrew/opt/zlib/lib/pkgconfig',
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
