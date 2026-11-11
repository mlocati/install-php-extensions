<?php

namespace IPE\Test\SystemPackager;

use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\SystemPackager\DNF;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class DNFTest extends TestCase
{
    /**
     * @var non-empty-string
     */
    const QUERYFORMAT_V4 = '--queryformat=%{name}|%{epoch}|%{version}|%{release}|%{arch}|%{sourcerpm}';

    /**
     * @var non-empty-string
     */
    const QUERYFORMAT_V5 = '--queryformat=%{name}|%{epoch}|%{version}|%{release}|%{arch}|%{sourcerpm}\n';

    /**
     * @param list<array{non-empty-string, list<string>, int, string, 4?: string}> $runs the command, its arguments, its exit code, its output, and how the output is handled (default: captured stdout)
     * @param bool $refresh
     *
     * @return \IPE\OS&\Mockery\MockInterface
     */
    private static function createOS(array $runs, $refresh = true)
    {
        $fs = M::mock(Filesystem::class);
        if ($refresh) {
            $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('dnf', ['makecache'])->withOutput(Command::OUTPUT_SHOW)))->andReturn([0, '']);
        }
        foreach ($runs as $run) {
            $command = Command::create($run[0], $run[1]);
            $output = isset($run[4]) ? $run[4] : Command::OUTPUT_CAPTURE_STDOUT;
            if ($output !== Command::OUTPUT_CAPTURE_ALL) {
                $command = $command->withOutput($output);
            }
            $fs->shouldReceive('run')->once()->with(self::isCommand($command))->andReturn([$run[2], $run[3]]);
        }
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getRawMachineArchitecture')->andReturn('x86_64');

        return $os;
    }

    /**
     * @param non-empty-string $queryFormat
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private static function repoquery($queryFormat, array $arguments)
    {
        return array_merge(['repoquery', '--quiet', '--available', '--latest-limit=1', $queryFormat], $arguments);
    }

    /**
     * @return list<array{string, class-string<\IPE\SystemPackager\DNF>}>
     */
    public static function provideImplementationClassCases()
    {
        return [
            ["dnf5 version 5.4.6.0\ndnf5 plugin API version 2.0\n", DNF\v5::class],
            ["4.14.0\n  Installed: dnf-0:4.14.0-34.el9_8.alma.1.noarch at Tue Oct 27 10:52:40 2026\n", DNF\v4::class],
        ];
    }

    /**
     * @dataProvider provideImplementationClassCases
     *
     * @param string $output
     * @param class-string<\IPE\SystemPackager\DNF> $expected
     *
     * @return void
     */
    public function testImplementationClass($output, $expected)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('dnf', ['--version'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT)))->andReturn([0, $output]);
        self::assertSame($expected, DNF::getImplementationClass($fs));
    }

    /**
     * @return list<array{int, string}>
     */
    public static function provideUnsupportedImplementationCases()
    {
        return [
            [0, "6.0.0\n"],
            [127, ''],
        ];
    }

    /**
     * @dataProvider provideUnsupportedImplementationCases
     *
     * @param int $rc
     * @param string $output
     *
     * @return void
     */
    public function testUnsupportedImplementation($rc, $output)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('dnf', ['--version'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT)))->andReturn([$rc, $output]);
        $this->expectException('IPE\Exception\SystemPackager');
        DNF::getImplementationClass($fs);
    }

    /**
     * @return void
     */
    public function testFindAvailablePackage()
    {
        $dnf = new DNF\v4(self::createOS([
            ['dnf', self::repoquery(self::QUERYFORMAT_V4, ['php-cli']), 0, "php-cli|0|8.0.30|8.el9_8.1|x86_64|php-8.0.30-8.el9_8.1.src.rpm\n"],
            ['dnf', self::repoquery(self::QUERYFORMAT_V4, ['make']), 0, "make|1|4.3|8.el9|x86_64|make-4.3-8.el9.src.rpm\n"],
            ['dnf', self::repoquery(self::QUERYFORMAT_V4, ['libzip-devel-1.7.3-8.el9']), 0, "libzip-devel|0|1.7.3|8.el9|i686|libzip-1.7.3-8.el9.src.rpm\nlibzip-devel|0|1.7.3|8.el9|x86_64|libzip-1.7.3-8.el9.src.rpm\n"],
        ]));
        $package = $dnf->findAvailablePackage('php-cli');
        self::assertNotNull($package);
        self::assertSame('php-cli', $package->getName());
        self::assertSame('8.0.30-8.el9_8.1', $package->getVersion());
        self::assertSame('php', $package->getSource());
        $package = $dnf->findAvailablePackage('make');
        self::assertNotNull($package);
        self::assertSame('1:4.3-8.el9', $package->getVersion());
        self::assertSame('make', $package->getSource());
        $package = $dnf->findAvailablePackage('libzip-devel', '1.7.3-8.el9');
        self::assertNotNull($package);
        self::assertSame('libzip-devel', $package->getName());
        self::assertSame('libzip', $package->getSource());
    }

    /**
     * @return void
     */
    public function testFindAvailableVirtualPackage()
    {
        $dnf = new DNF\v5(self::createOS([
            ['dnf', self::repoquery(self::QUERYFORMAT_V5, ['php-redis']), 0, ''],
            ['dnf', self::repoquery(self::QUERYFORMAT_V5, ['--whatprovides', 'php-redis']), 0, "php-pecl-redis6|0|6.3.0|2.fc44|x86_64|php-pecl-redis6-6.3.0-2.fc44.src.rpm\n"],
            ['dnf', self::repoquery(self::QUERYFORMAT_V5, ['php-pecl-redis6']), 0, "php-pecl-redis6|0|6.3.0|2.fc44|x86_64|php-pecl-redis6-6.3.0-2.fc44.src.rpm\n"],
        ]));
        $package = $dnf->findAvailablePackage('php-redis');
        self::assertNotNull($package);
        self::assertSame('php-pecl-redis6', $package->getName());
        self::assertSame('6.3.0-2.fc44', $package->getVersion());
        self::assertSame('php-pecl-redis6', $package->getSource());
    }

    /**
     * @return void
     */
    public function testPackageNotAvailable()
    {
        $dnf = new DNF\v5(self::createOS([
            ['dnf', self::repoquery(self::QUERYFORMAT_V5, ['php-nope']), 0, ''],
            ['dnf', self::repoquery(self::QUERYFORMAT_V5, ['--whatprovides', 'php-nope']), 0, ''],
            ['dnf', self::repoquery(self::QUERYFORMAT_V5, ['php-foo']), 0, ''],
            ['dnf', self::repoquery(self::QUERYFORMAT_V5, ['--whatprovides', 'php-foo']), 0, "php-foo-a|0|1.0|1.fc44|x86_64|php-foo-a-1.0-1.fc44.src.rpm\nphp-foo-b|0|2.0|1.fc44|x86_64|php-foo-b-2.0-1.fc44.src.rpm\n"],
        ]));
        self::assertNull($dnf->findAvailablePackage('php-nope'));
        // Provided by more packages
        self::assertNull($dnf->findAvailablePackage('php-foo'));
    }

    /**
     * @return list<array{non-empty-string, string}>
     */
    public static function provideUpstreamVersionCases()
    {
        return [
            ['8.0.30-8.el9_8.1', '8.0.30'],
            ['1:4.3-8.el9', '4.3'],
            ['3.1-39.20210216cvs.el9', '3.1'],
            ['6.3.0', '6.3.0'],
            ['abc-1', ''],
        ];
    }

    /**
     * @dataProvider provideUpstreamVersionCases
     *
     * @param non-empty-string $version
     * @param string $expected
     *
     * @return void
     */
    public function testUpstreamVersion($version, $expected)
    {
        self::assertSame($expected, (new DNF\v4(M::mock(OS::class)))->getUpstreamVersion($version));
    }

    /**
     * @return void
     */
    public function testExpandPackageListWithDNF4()
    {
        $output = <<<'EOT'
Last metadata expiration check: 0:00:02 ago on Wed Nov 11 22:14:17 2026.
Dependencies resolved.
================================================================================
 Package                  Arch     Version                    Repository   Size
================================================================================
Installing:
 php-devel                x86_64   8.0.30-8.el9_8.1           appstream   658 k
Upgrading:
 openssl-libs             x86_64   1:3.5.8-2.el9_8            baseos      2.3 M
Installing dependencies:
 python3-dnf-plugin-post-transaction-actions
                          noarch   4.3.0-26.el9               baseos       16 k
 make                     x86_64   1:4.3-8.el9                baseos      530 k
Enabling module streams:
 php                               8.1

Transaction Summary
================================================================================
Install  3 Packages
Upgrade  1 Package

Total download size: 4 M
Operation aborted.
EOT;
        $dnf = new DNF\v4(self::createOS([
            ['dnf', ['install', '--assumeno', '--setopt=install_weak_deps=False', 'php-devel', 'make-1:4.3-8.el9'], 1, $output, Command::OUTPUT_CAPTURE_ALL],
        ]));
        self::assertSame(
            [
                'php-devel' => '8.0.30-8.el9_8.1',
                'openssl-libs' => '1:3.5.8-2.el9_8',
                'python3-dnf-plugin-post-transaction-actions' => '4.3.0-26.el9',
                'make' => '1:4.3-8.el9',
            ],
            $dnf->expandPackageList(['php-devel', 'make-1:4.3-8.el9'])
        );
    }

    /**
     * @return void
     */
    public function testExpandPackageListWithDNF5()
    {
        $output = <<<'EOT'
Updating and loading repositories:
Repositories loaded.
Package                      Arch   Version                   Repository                            Size
Upgrading:
 pcre2                       x86_64 0:10.49-1.fc44            updates                          726.5 KiB
   replacing pcre2           x86_64 0:10.47-1.fc44.1          e47173b441304a5c948252c4be76fe8d 718.6 KiB
Installing:
 php-cli                     x86_64 0:8.5.12-1.fc44           updates                           25.8 MiB
Installing dependencies:
 emacs-filesystem            x86_64 1:30.2-2.fc44             fedora                             0.0   B

Transaction Summary:
 Installing:         2 packages
 Upgrading:          1 package
 Replacing:          1 package

Operation aborted by the user.
EOT;
        $dnf = new DNF\v5(self::createOS([
            ['dnf', ['install', '--assumeno', '--setopt=install_weak_deps=False', 'php-cli', 'pkgconfig(libzip)'], 1, $output, Command::OUTPUT_CAPTURE_ALL],
        ]));
        self::assertSame(
            [
                'pcre2' => '10.49-1.fc44',
                'php-cli' => '8.5.12-1.fc44',
                'emacs-filesystem' => '1:30.2-2.fc44',
            ],
            $dnf->expandPackageList(['php-cli', 'pkgconfig(libzip)'])
        );
    }

    /**
     * @return void
     */
    public function testExpandPackageListFailure()
    {
        $output = "Updating and loading repositories:\nRepositories loaded.\nFailed to resolve the transaction:\nNo match for argument: php-nope\n";
        $dnf = new DNF\v5(self::createOS([
            ['dnf', ['install', '--assumeno', '--setopt=install_weak_deps=False', 'php-nope'], 1, $output, Command::OUTPUT_CAPTURE_ALL],
        ]));
        $this->expectException('IPE\Exception\SystemPackager');
        $dnf->expandPackageList(['php-nope']);
    }

    /**
     * @return void
     */
    public function testInstalledPackages()
    {
        $dnf = new DNF\v4(self::createOS([
            ['rpm', ['--query', '--all', '--queryformat=%{NAME}\n'], 0, "gpg-pubkey\ntzdata\ngpg-pubkey\nphp-cli\n"],
        ], false));
        self::assertSame(['gpg-pubkey', 'tzdata', 'php-cli'], $dnf->listInstalledPackages());
    }

    /**
     * @return void
     */
    public function testInstalledPackageOwningFile()
    {
        $dnf = new DNF\v4(self::createOS([
            ['rpm', ['--query', '--file', '--queryformat=%{NAME}|%{EPOCH}|%{VERSION}|%{RELEASE}|%{SOURCERPM}\n', '/usr/bin/php'], 0, "php-cli|(none)|8.0.30|8.el9_8.1|php-8.0.30-8.el9_8.1.src.rpm\n"],
            ['rpm', ['--query', '--file', '--queryformat=%{NAME}|%{EPOCH}|%{VERSION}|%{RELEASE}|%{SOURCERPM}\n', '/usr/local/bin/php'], 1, "file /usr/local/bin/php is not owned by any package\n"],
        ], false));
        $package = $dnf->getInstalledPackageOwningFile('/usr/bin/php');
        self::assertNotNull($package);
        self::assertSame('php-cli', $package->getName());
        self::assertSame('8.0.30-8.el9_8.1', $package->getVersion());
        self::assertSame('php', $package->getSource());
        self::assertNull($dnf->getInstalledPackageOwningFile('/usr/local/bin/php'));
    }

    /**
     * @return void
     */
    public function testMarkAsManuallyInstalled()
    {
        $dnf4 = new DNF\v4(self::createOS([
            ['dnf', ['mark', 'install', 'php-common'], 0, '', Command::OUTPUT_SHOW],
        ], false));
        $dnf4->markAsManuallyInstalled(['php-common']);
        $dnf5 = new DNF\v5(self::createOS([
            ['dnf', ['mark', 'user', '-y', 'php-common'], 0, '', Command::OUTPUT_SHOW],
        ], false));
        $dnf5->markAsManuallyInstalled(['php-common']);
    }

    /**
     * @return void
     */
    public function testRestorePackages()
    {
        $dnf4 = new DNF\v4(self::createOS([
            ['dnf', ['install', '-y', '--setopt=install_weak_deps=False', '--setopt=strict=False', 'gpg-pubkey', 'libedit'], 0, '', Command::OUTPUT_SHOW],
        ], false));
        $dnf4->restorePackages(['gpg-pubkey', 'libedit']);
        $dnf5 = new DNF\v5(self::createOS([
            ['dnf', ['install', '-y', '--setopt=install_weak_deps=False', '--skip-unavailable', 'gpg-pubkey', 'libedit'], 0, '', Command::OUTPUT_SHOW],
        ], false));
        $dnf5->restorePackages(['gpg-pubkey', 'libedit']);
    }

    /**
     * @return list<array{non-empty-string, bool, bool}>
     */
    public static function providePackageNameCases()
    {
        return [
            ['php-cli', false, true],
            ['libstdc++-devel', false, true],
            ['pkgconfig(libzip)', false, true],
            ['php-cli-8.0.30-8.el9_8.1', false, true],
            ['make-1:4.3-8.el9', true, true],
            ['make-1:4.3-8.el9', false, false],
            ['-php', true, false],
            ['php cli', true, false],
        ];
    }

    /**
     * @dataProvider providePackageNameCases
     *
     * @param non-empty-string $name
     * @param bool $mayIncludeVersion
     * @param bool $expected
     *
     * @return void
     */
    public function testPackageNames($name, $mayIncludeVersion, $expected)
    {
        $dnf = new DNF\v4(M::mock(OS::class));
        $method = new \ReflectionMethod($dnf, 'validatePackageName');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        self::assertSame($expected, $method->invoke($dnf, $name, $mayIncludeVersion));
    }
}
